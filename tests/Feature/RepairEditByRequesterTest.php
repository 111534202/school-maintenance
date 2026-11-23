<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 11/24 劉家芸提出：只有報修權的人（例如學生）在還沒派工前可以修改自己報修單的故障描述；
 * 報修看板的維修人員搜尋併進關鍵字；設備條碼框只有相機掃到時才顯示綠色的「掃描到的設備」。
 */
class RepairEditByRequesterTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function student(): User
    {
        return $this->makeUserWithPermissions(['repairs.create']);
    }

    public function test_the_requester_can_edit_the_description_before_dispatch(): void
    {
        $student = $this->student();
        $repair = RepairRequest::factory()->create(['requester_id' => $student->id, 'status' => 'pending', 'title' => '原標題', 'description' => '舊描述']);

        $this->actingAs($student)->get(route('repairs.show', $repair))->assertOk()->assertSee(route('repairs.edit', $repair), false);
        $this->actingAs($student)->get(route('repairs.edit', $repair))->assertOk()->assertSee('還沒派工前可以補充或修改故障描述');

        $this->actingAs($student)->put(route('repairs.update', $repair), ['title' => '想偷改標題', 'description' => '新描述'])->assertRedirect();

        $repair->refresh();
        $this->assertSame('新描述', $repair->description);
        $this->assertSame('原標題', $repair->title);
    }

    public function test_the_requester_cannot_edit_after_dispatch_or_someone_elses_repair(): void
    {
        $student = $this->student();
        $assigned = RepairRequest::factory()->create(['requester_id' => $student->id, 'status' => 'assigned']);
        $others = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($student)->get(route('repairs.edit', $assigned))->assertForbidden();
        $this->actingAs($student)->put(route('repairs.update', $assigned), ['description' => 'x'])->assertForbidden();
        $this->actingAs($student)->get(route('repairs.show', $assigned))->assertOk()->assertDontSee(route('repairs.edit', $assigned), false);
        $this->actingAs($student)->put(route('repairs.update', $others), ['description' => 'x'])->assertForbidden();
    }

    public function test_the_keyword_also_searches_the_assigned_technician(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $tech = $this->makeUserWithRole('technician', '維修人員', ['name' => '王小修']);
        RepairRequest::factory()->create(['title' => '王的單', 'assigned_to' => $tech->id, 'status' => 'assigned']);
        RepairRequest::factory()->create(['title' => '別人的單', 'status' => 'pending']);

        $html = $this->actingAs($admin)->get(route('repairs.index', ['keyword' => '王小修']))->assertOk()->getContent();
        $this->assertStringContainsString('王的單', $html);
        $this->assertStringNotContainsString('別人的單', $html);
        $this->assertStringNotContainsString('name="assignee"', $html);
    }

    public function test_the_scanned_device_box_only_shows_after_a_camera_scan(): void
    {
        $partial = file_get_contents(resource_path('views/repairs/_device-scan.blade.php'));
        $this->assertStringContainsString("displayBox.style.display = fromCamera ? '' : 'none'", $partial);
    }

    // 從設備主檔點過來時關鍵字是完整設備編號：只找這台，不會連 DEV-00011 也找到。
    public function test_a_full_device_code_keyword_only_matches_that_device(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $room = \App\Models\Classroom::create(['classroom_code' => 'KW-1', 'campus' => '本', 'building' => 'K', 'floor' => '1', 'name' => 'KW 教室']);
        $category = \App\Models\DeviceCategory::create(['name' => '關鍵字測試']);
        $make = fn ($code) => \App\Models\Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
        $a = $make('KW-0001');
        $b = $make('KW-00011');
        RepairRequest::factory()->create(['title' => '甲單', 'device_id' => $a->id]);
        RepairRequest::factory()->create(['title' => '乙單', 'device_id' => $b->id]);

        $html = $this->actingAs($admin)->get(route('repairs.index', ['keyword' => 'KW-0001']))->assertOk()->getContent();
        $this->assertStringContainsString('甲單', $html);
        $this->assertStringNotContainsString('乙單', $html);
    }

    // 維修紀錄、保養結果的時間選擇器不限制可選日期，送出時才檢查。
    public function test_time_pickers_are_not_limited_and_are_checked_on_submit(): void
    {
        $log = file_get_contents(resource_path('views/repair-logs/create.blade.php'));
        $this->assertStringNotContainsString('min="{{', $log);
        $this->assertStringNotContainsString('.max =', $log);
        $this->assertStringContainsString('開始時間不能早於報修申請的時間', $log);
        $result = file_get_contents(resource_path('views/maintenance_results/create.blade.php'));
        $this->assertStringNotContainsString('executed.max =', $result);
    }

    private function roomAndDevice(): array
    {
        $category = \App\Models\DeviceCategory::create(['name' => '地點測試']);
        $a = \App\Models\Classroom::create(['classroom_code' => 'LOC-A', 'campus' => '本', 'building' => 'L', 'floor' => '1', 'name' => '甲教室']);
        $b = \App\Models\Classroom::create(['classroom_code' => 'LOC-B', 'campus' => '本', 'building' => 'L', 'floor' => '2', 'name' => '乙教室']);
        $device = \App\Models\Device::create(['device_code' => 'LOC-001', 'device_category_id' => $category->id, 'classroom_id' => $a->id, 'status' => 'normal']);

        return [$a, $b, $device];
    }

    // 有選設備、發現地點改成別間：沒勾確認不能送出；勾了才可以。發現地點就是設備的教室則不用勾。
    public function test_a_different_location_from_the_device_needs_confirmation(): void
    {
        [$a, $b, $device] = $this->roomAndDevice();
        $user = $this->makeUserWithRole('admin', '系統管理員');
        $base = ['title' => '地點不同', 'description' => 'x', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_id' => $device->id];

        $this->actingAs($user)->post(route('repairs.store'), $base + ['classroom_id' => $b->id])->assertSessionHasErrors('classroom_id');
        $this->assertSame(0, RepairRequest::count());

        $this->actingAs($user)->post(route('repairs.store'), $base + ['classroom_id' => $b->id, 'location_differs_confirmed' => 1])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('repairs.store'), $base + ['classroom_id' => $a->id])->assertSessionHasNoErrors();
        $this->assertSame(2, RepairRequest::count());

        $html = $this->actingAs($user)->get(route('repairs.create'))->getContent();
        $this->assertStringContainsString('確認發現地點與設備登記的教室不同', $html);
    }

    // 修改報修單：改發現地點成和設備不同要勾確認；原本就不同、這次沒改的不用。
    public function test_editing_the_location_away_from_the_device_needs_confirmation(): void
    {
        [$a, $b, $device] = $this->roomAndDevice();
        $user = $this->makeUserWithRole('admin', '系統管理員');
        $repair = RepairRequest::factory()->create(['device_id' => $device->id, 'classroom_id' => $a->id, 'status' => 'pending']);
        $form = ['title' => '改地點', 'description' => 'x', 'impact_level' => 'low', 'is_class_affected' => 0];

        $this->actingAs($user)->put(route('repairs.update', $repair), $form + ['classroom_id' => $b->id])->assertSessionHasErrors('classroom_id');
        $this->actingAs($user)->put(route('repairs.update', $repair), $form + ['classroom_id' => $b->id, 'location_differs_confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame($b->id, $repair->refresh()->classroom_id);

        // 已經是不同的教室，這次沒改：直接儲存
        $this->actingAs($user)->put(route('repairs.update', $repair), $form + ['classroom_id' => $b->id])->assertSessionHasNoErrors();
    }
}
