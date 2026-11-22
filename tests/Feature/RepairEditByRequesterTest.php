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
}
