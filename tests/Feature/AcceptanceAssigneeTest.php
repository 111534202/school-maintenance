<?php

namespace Tests\Feature;

use App\Enums\RepairRequestStatus;
use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 驗收指派專人：派工時可指定驗收人；除了管理員，只有跟自己有關的人看得到「待驗收」「已結案」的案件；
 * 沒有驗收權限的人看不到這兩個狀態的篩選選項。另外：零件單價只有零件庫存權限的人看得到。
 */
class AcceptanceAssigneeTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function repair(array $attributes = []): RepairRequest
    {
        return RepairRequest::factory()->create($attributes + ['status' => RepairRequestStatus::PendingReview->value]);
    }

    // ---------- 指派驗收人 ----------

    public function test_the_dispatcher_can_pick_an_acceptor_and_only_that_person_can_accept(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $acceptor = $this->makeUserWithRole('executive', '主管');
        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => RepairRequestStatus::Pending->value, 'requester_id' => $teacher->id]);

        $this->actingAs($this->makeItManager())->post(route('repairs.assign', $repair), ['assigned_to' => $technician->id, 'accepted_by' => $acceptor->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($acceptor->id, $repair->fresh()->accepted_by);

        $repair->update(['status' => RepairRequestStatus::PendingReview->value]);
        // 報修人本人不再是驗收人（他仍有驗收權限，但已指定別人）：不能驗收；被指定的人可以。
        $this->actingAs($teacher)->post(route('repairs.complete', $repair))->assertForbidden();
        $this->actingAs($acceptor)->post(route('repairs.complete', $repair))->assertRedirect();
        $this->assertSame(RepairRequestStatus::Completed, $repair->fresh()->status);
    }

    public function test_without_an_acceptor_the_requester_accepts_as_before(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $other = User::factory()->create(['role_id' => $teacher->role_id]);
        $repair = $this->repair(['requester_id' => $teacher->id, 'accepted_by' => null]);

        $this->actingAs($other)->post(route('repairs.complete', $repair))->assertForbidden();
        $this->actingAs($teacher)->post(route('repairs.complete', $repair))->assertRedirect();
    }

    public function test_the_acceptor_must_be_an_active_person_with_the_accept_permission(): void
    {
        $technician = $this->makeTechnician();   // 維修人員身分沒有驗收權限
        $repair = RepairRequest::factory()->create(['status' => RepairRequestStatus::Pending->value]);
        $this->actingAs($this->makeItManager());

        $this->post(route('repairs.assign', $repair), ['assigned_to' => $technician->id, 'accepted_by' => $technician->id])->assertSessionHasErrors('accepted_by');
        $this->assertNull($repair->fresh()->accepted_by);
    }

    // ---------- 誰看得到驗收階段的案件 ----------

    public function test_only_related_people_and_the_admin_see_pending_review_and_completed_cases(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $acceptor = $this->makeUserWithRole('executive', '主管');
        $technician = $this->makeTechnician();
        $manager = $this->makeItManager();
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair(['title' => '待驗收的投影機', 'requester_id' => $teacher->id, 'assigned_to' => $technician->id, 'accepted_by' => $acceptor->id]);
        $this->repair(['title' => '已結案的冷氣', 'status' => RepairRequestStatus::Completed->value, 'requester_id' => $teacher->id, 'assigned_to' => $technician->id, 'accepted_by' => $acceptor->id]);
        RepairRequest::factory()->create(['title' => '處理中的電腦', 'status' => RepairRequestStatus::InProgress->value, 'requester_id' => $teacher->id]);

        foreach ([$teacher, $acceptor, $technician, $admin] as $related) {
            $this->actingAs($related)->get(route('repairs.index'))->assertSee('待驗收的投影機')->assertSee('已結案的冷氣');
        }

        // 能派工的管理員身分（設備管理員）不是相關的人：看不到驗收階段的案件，但其他狀態照常看全部。
        $this->actingAs($manager)->get(route('repairs.index'))->assertDontSee('待驗收的投影機')->assertDontSee('已結案的冷氣')->assertSee('處理中的電腦');
    }

    public function test_an_unrelated_person_cannot_open_an_acceptance_stage_case_but_can_open_the_other_ones(): void
    {
        $manager = $this->makeItManager();
        $pendingReview = $this->repair();
        $inProgress = RepairRequest::factory()->create(['status' => RepairRequestStatus::InProgress->value]);

        $this->actingAs($manager)->get(route('repairs.show', $pendingReview))->assertForbidden();
        $this->actingAs($manager)->get(route('repairs.show', $inProgress))->assertOk();
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route('repairs.show', $pendingReview))->assertOk();
    }

    public function test_people_without_the_accept_permission_do_not_get_the_two_status_options(): void
    {
        $technician = $this->makeTechnician();
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->actingAs($technician)->get(route('repairs.index'))->assertOk()
            ->assertDontSee('value="pending_review"', false)->assertDontSee('value="completed"', false)->assertSee('value="in_progress"', false);
        $this->actingAs($teacher)->get(route('repairs.index'))->assertSee('value="pending_review"', false)->assertSee('value="completed"', false);
    }

    public function test_the_bell_counts_only_the_cases_the_person_can_accept(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $acceptor = $this->makeUserWithRole('executive', '主管');
        $this->repair(['requester_id' => $teacher->id, 'accepted_by' => $acceptor->id]);
        $this->repair(['requester_id' => $teacher->id, 'accepted_by' => null]);
        $this->repair(['requester_id' => $teacher->id, 'accepted_by' => $acceptor->id]);

        // 報修人只算「沒指定驗收人」的那 1 張；被指定的人（主管）算指定給他的 2 張；管理員全部 3 張。
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('1 張工單待驗收');
        $this->actingAs($acceptor)->get(route('dashboard'))->assertOk()->assertSee('2 張工單待驗收');
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route('dashboard'))->assertOk()->assertSee('3 張工單待驗收');
    }

    // ---------- 零件單價 ----------

    public function test_part_prices_are_only_shown_to_people_with_the_parts_permission(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $repair = RepairRequest::factory()->create(['requester_id' => $teacher->id, 'status' => RepairRequestStatus::InProgress->value]);
        $log = RepairLog::factory()->create(['repair_request_id' => $repair->id]);
        $part = Part::create(['part_code' => 'P-1', 'name' => '秘密零件', 'unit' => '個', 'safety_stock' => 1]);
        $purchase = $part->purchases()->create(['purchase_date' => now()->toDateString(), 'vendor_name' => 'V', 'quantity' => 5, 'unit_price' => 12345, 'remaining_quantity' => 4]);
        RepairPartUsage::create(['repair_log_id' => $log->id, 'part_id' => $part->id, 'part_purchase_id' => $purchase->id, 'quantity' => 1, 'unit_price' => 12345]);

        // 報修人：看得到用了什麼零件、幾個，看不到單價、小計、材料費總額。
        $this->actingAs($teacher)->get(route('repairs.show', $repair))->assertOk()
            ->assertSee('秘密零件')->assertDontSee('12,345')->assertDontSee('單價')->assertDontSee('材料費總額');
        // 有零件庫存權限的設備管理員：全部看得到。
        $this->actingAs($this->makeItManager())->get(route('repairs.show', $repair))->assertSee('秘密零件')->assertSee('12,345')->assertSee('材料費總額');
    }
}
