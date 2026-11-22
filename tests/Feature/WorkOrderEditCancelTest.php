<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use App\Services\DueMaintenanceOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 工單（報修單、保養工單）的修改與取消（刪除）：
 * - 修改：只有有派工權限（報修單 repairs.dispatch）／保養管理權限（保養工單 maintenance.manage）的人能改；已結案、已完成的不能改。
 * - 取消與刪除是同一件事，做法是軟刪除：看板與統計不再列出，資料與操作紀錄保留；已經有維修紀錄、保養結果的不能取消。
 */
class WorkOrderEditCancelTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function dispatcher()
    {
        return $this->makeUserWithPermissions(['repairs.dispatch', 'repairs.create']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => '修改後的標題', 'description' => '修改後的描述', 'impact_level' => 'high',
            'is_class_affected' => '1', 'device_note' => '三樓走廊投影機', 'classroom_id' => '',
        ], $override);
    }

    // ---------- 報修單：修改 ----------

    public function test_a_dispatcher_can_edit_a_repair_request_and_it_is_logged(): void
    {
        $repair = RepairRequest::factory()->create(['title' => '原標題', 'status' => 'assigned', 'device_note' => '原設備']);
        $this->actingAs($this->dispatcher());

        $this->get(route('repairs.edit', $repair))->assertOk()->assertSee('原標題');
        $this->put(route('repairs.update', $repair), $this->payload())->assertRedirect(route('repairs.show', $repair))->assertSessionHas('success');

        $repair->refresh();
        $this->assertSame('修改後的標題', $repair->title);
        $this->assertSame('high', $repair->impact_level);
        $this->assertSame('assigned', $repair->status->value);   // 狀態不會被改動
        $log = AuditLog::where('action', 'updated')->where('loggable_id', $repair->id)->firstOrFail();
        $this->assertSame('原標題', $log->changes['title']['old']);
    }

    public function test_editing_still_needs_device_note_or_classroom_when_no_device_is_bound(): void
    {
        $repair = RepairRequest::factory()->create(['device_id' => null, 'status' => 'pending']);
        $this->actingAs($this->dispatcher());

        $this->put(route('repairs.update', $repair), $this->payload(['device_note' => '', 'classroom_id' => '']))->assertSessionHasErrors('device_note');
    }

    public function test_people_without_dispatch_permission_cannot_edit_or_cancel(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'pending']);
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.edit', $repair))->assertForbidden();
        $this->put(route('repairs.update', $repair), $this->payload())->assertForbidden();
        $this->post(route('repairs.return', $repair), ['return_reason' => '不用修'])->assertForbidden();
        $this->assertSame('pending', $repair->fresh()->status->value);
    }

    public function test_a_completed_repair_request_cannot_be_edited(): void
    {
        $dispatcher = $this->dispatcher();
        // 已結案的案件，派工者也要「跟案件有關」才看得到（這裡指定由他驗收）。
        $repair = RepairRequest::factory()->create(['title' => '已結案的單', 'status' => 'completed', 'accepted_by' => $dispatcher->id]);
        $this->actingAs($dispatcher);

        $this->put(route('repairs.update', $repair), $this->payload())->assertSessionHas('error');

        $this->assertSame('已結案的單', $repair->fresh()->title);
    }

    // ---------- 報修單：退回（取代原本的取消／刪除） ----------

    // 能派工的人可以退回新報修：必填原因，狀態變「已退回」，仍留在看板（報修單不能再刪除）。
    public function test_a_dispatcher_can_return_a_new_request_with_a_reason_and_it_stays_on_the_board(): void
    {
        $repair = RepairRequest::factory()->create(['title' => '要退回的單', 'status' => 'pending']);
        $dispatcher = $this->dispatcher();
        $this->actingAs($dispatcher);

        $this->post(route('repairs.return', $repair), ['return_reason' => ''])->assertSessionHasErrors('return_reason');
        $this->assertSame('pending', $repair->fresh()->status->value);

        $this->post(route('repairs.return', $repair), ['return_reason' => '不是設備問題'])
            ->assertRedirect(route('repairs.show', $repair))->assertSessionHas('success');

        $repair->refresh();
        $this->assertSame('returned', $repair->status->value);
        $this->assertSame('不是設備問題', $repair->return_reason);
        $this->assertSame($dispatcher->id, $repair->returned_by);
        $this->assertNotNull($repair->returned_at);
        $this->assertNull($repair->deleted_at);
        $this->get(route('repairs.index'))->assertSee('要退回的單');
        $this->get(route('repairs.show', $repair))->assertSee('退回原因：')->assertSee('不是設備問題');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('repairs.destroy'), '報修單不能刪除');
    }

    // 已派工的報修單：被指派的維修人員、能派工的人都能退回；別的維修人員不行。新報修只有能派工的人能退回。
    public function test_an_assigned_request_can_be_returned_by_its_technician_or_a_dispatcher(): void
    {
        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);

        $this->actingAs($this->makeTechnician());
        $this->post(route('repairs.return', $repair), ['return_reason' => 'x'])->assertForbidden();

        $this->actingAs($technician);
        $this->post(route('repairs.return', $repair), ['return_reason' => '需要廠商處理'])->assertSessionHas('success');
        $this->assertSame('returned', $repair->fresh()->status->value);

        $another = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        $this->actingAs($this->dispatcher());
        $this->post(route('repairs.return', $another), ['return_reason' => '重複報修'])->assertSessionHas('success');
        $this->assertSame('returned', $another->fresh()->status->value);

        $pending = RepairRequest::factory()->create(['status' => 'pending']);
        $this->actingAs($technician);
        $this->post(route('repairs.return', $pending), ['return_reason' => 'x'])->assertForbidden();
    }

    // 待驗收、已結案、已退回都不能再退回。
    public function test_requests_past_assignment_cannot_be_returned(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        foreach (['pending_review', 'completed', 'returned'] as $status) {
            $repair = RepairRequest::factory()->create(['status' => $status]);
            $this->post(route('repairs.return', $repair), ['return_reason' => 'x'])->assertForbidden();
            $this->assertSame($status, $repair->fresh()->status->value);
        }
    }

    public function test_show_page_offers_edit_and_return_only_when_allowed(): void
    {
        $dispatcher = $this->dispatcher();
        $pending = RepairRequest::factory()->create(['status' => 'pending']);
        // 待驗收的案件，能派工的人也只有「跟自己有關」才看得到：這裡指定由他驗收。
        $review = RepairRequest::factory()->create(['status' => 'pending_review', 'accepted_by' => $dispatcher->id]);
        $this->actingAs($dispatcher);

        $this->get(route('repairs.show', $pending))->assertSee('退回報修單')->assertDontSee('取消工單')->assertSee(route('repairs.edit', $pending), false);
        $this->get(route('repairs.show', $review))->assertDontSee('退回報修單')->assertSee(route('repairs.edit', $review), false);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('repairs.show', $pending))->assertDontSee('退回報修單')->assertDontSee(route('repairs.edit', $pending), false);
    }

    // ---------- 保養工單 ----------

    private function manager()
    {
        return $this->makeUserWithPermissions(['maintenance.manage', 'maintenance.view']);
    }

    public function test_a_maintenance_manager_can_change_the_scheduled_date(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => '2027-01-10']);
        $this->actingAs($this->manager());

        $this->get(route('maintenance-orders.edit', $order))->assertOk();
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2027-02-01'])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame('2027-02-01', $order->fresh()->scheduled_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'loggable_id' => $order->id]);
    }

    public function test_maintenance_order_edit_and_cancel_need_the_manage_permission(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->makeUserWithRole('technician', '維修人員'));   // 只有 maintenance.view／report

        $this->get(route('maintenance-orders.edit', $order))->assertForbidden();
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2027-02-01'])->assertForbidden();
        $this->delete(route('maintenance-orders.destroy', $order))->assertForbidden();
        $this->assertNull($order->fresh()->deleted_at);
    }

    // 取消保養工單＝改成「已中止」（保留紀錄、唯讀）：列表平常不顯示，篩選「已中止」才看得到；排程不會重新產生那一期。
    public function test_cancelling_a_maintenance_order_makes_it_aborted_and_hidden_unless_filtered(): void
    {
        $plan = MaintenancePlan::factory()->create(['is_active' => true, 'next_due_date' => now()->toDateString()]);
        $order = MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date,
        ]);
        $this->actingAs($this->manager());

        $this->delete(route('maintenance-orders.destroy', $order))->assertRedirect(route('maintenance-orders.index'))->assertSessionHas('success');

        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $order->fresh()->status);
        $this->assertNull($order->fresh()->deleted_at);
        $this->get(route('maintenance-orders.index'));   // 第一次會顯示「已取消」的提示訊息（含編號）
        $this->get(route('maintenance-orders.index'))->assertDontSee($order->displayNumber());
        $this->get(route('maintenance-orders.index', ['status' => 'aborted']))->assertSee($order->displayNumber());
        $this->get(route('maintenance-orders.show', $order))->assertSee('已中止');
        $this->delete(route('maintenance-orders.destroy', $order))->assertSessionHas('error');   // 已中止不能再取消
        // 排程不會因為取消了，又把同一期的工單重新產生出來。
        $this->assertSame(0, app(DueMaintenanceOrderGenerator::class)->generate());
        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());
    }

    public function test_completed_or_reported_maintenance_orders_cannot_be_edited_or_cancelled(): void
    {
        $this->actingAs($this->manager());

        $done = MaintenanceOrder::factory()->create(['status' => 'completed']);
        $this->put(route('maintenance-orders.update', $done), ['scheduled_date' => '2027-02-01'])->assertSessionHas('error');
        $this->delete(route('maintenance-orders.destroy', $done))->assertSessionHas('error');
        $this->assertNull($done->fresh()->deleted_at);

        $reported = MaintenanceOrder::factory()->create(['status' => 'in_progress']);
        MaintenanceResult::factory()->create(['maintenance_order_id' => $reported->id]);
        $this->delete(route('maintenance-orders.destroy', $reported))->assertSessionHas('error');
        $this->assertNull($reported->fresh()->deleted_at);
    }

}
