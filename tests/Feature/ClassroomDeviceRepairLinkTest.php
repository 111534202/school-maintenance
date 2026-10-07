<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Services\DeviceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 教室主檔 ⇄ 設備主檔 ⇄ 報修單 的連動顯示：
 * 教室列表顯示每間教室的設備數、異常設備數、進行中報修單數，並連到設備主檔與報修看板；
 * 「異常設備」的定義只有一份（DeviceStatusService::PROBLEM_STATUSES：維修中、已淘汰、停用）。
 */
class ClassroomDeviceRepairLinkTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private DeviceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
        $this->category = DeviceCategory::create(['name' => '投影機']);
    }

    private function room(string $code, array $attrs = []): Classroom
    {
        return Classroom::create(array_merge(['campus' => '本', 'building' => 'A', 'floor' => '1', 'room_code' => $code, 'room_name' => "$code 教室"], $attrs));
    }

    private function device(Classroom $room, string $code, string $status = 'normal', array $attrs = []): Device
    {
        return Device::create(array_merge([
            'device_code' => $code, 'device_category_id' => $this->category->id, 'classroom_id' => $room->id, 'status' => $status,
        ], $attrs));
    }

    private function repair(Device $device, string $status = 'pending', array $attrs = []): RepairRequest
    {
        return RepairRequest::factory()->create(array_merge(['device_id' => $device->id, 'status' => $status], $attrs));
    }

    /** 輔助方法：把教室列表頁解析成「教室代碼 => [每一欄的文字]」，方便逐格檢查。 */
    private function classroomRows(array $query = []): array
    {
        $html = $this->get(route('classrooms.index', $query))->assertOk()->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $rows = [];
        foreach ($dom->getElementsByTagName('tbody') as $tbody) {
            foreach ($tbody->getElementsByTagName('tr') as $tr) {
                $cells = [];
                foreach ($tr->getElementsByTagName('td') as $td) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $td->textContent));
                }
                if (count($cells) > 1) {
                    $rows[$cells[0]] = ['cells' => $cells, 'class' => $tr->getAttribute('class'), 'html' => $dom->saveHTML($tr)];
                }
            }
        }

        return $rows;
    }

    // 教室列表顯示每間教室的設備數、異常設備數、進行中報修單數（已結案的不算進行中）。
    public function test_classroom_list_shows_device_abnormal_and_open_repair_counts(): void
    {
        $a = $this->room('A101');
        $this->device($a, 'A-OK');
        $broken = $this->device($a, 'A-BROKEN', 'repairing');
        $this->repair($broken, 'in_progress');
        $this->repair($broken, 'completed');                  // 已結案，不算進行中
        $b = $this->room('B202');
        $this->device($b, 'B-OK');

        $rows = $this->classroomRows();

        // 欄位順序：代碼、名稱、部門、位置、管理人、設備數、異常設備、進行中工單、狀態、操作
        $this->assertSame('2', $rows['A101']['cells'][5]);
        $this->assertSame('1', $rows['A101']['cells'][6]);
        $this->assertSame('1', $rows['A101']['cells'][7]);
        $this->assertSame('1', $rows['B202']['cells'][5]);
        $this->assertSame('0', $rows['B202']['cells'][6]);
        $this->assertSame('0', $rows['B202']['cells'][7]);
    }

    // 有異常設備的教室整列標紅；沒有的不標。
    public function test_rooms_with_abnormal_devices_are_highlighted(): void
    {
        $this->device($this->room('A101'), 'A-BROKEN', 'repairing');
        $this->device($this->room('B202'), 'B-OK');

        $rows = $this->classroomRows();

        $this->assertStringContainsString('table-danger', $rows['A101']['class']);
        $this->assertStringNotContainsString('table-danger', $rows['B202']['class']);
    }

    // 「異常」就是維修中、已淘汰、停用三種；正常的不算。
    public function test_abnormal_means_repairing_retired_or_disabled(): void
    {
        $room = $this->room('A101');
        $this->device($room, 'D-1', 'normal');
        $this->device($room, 'D-2', 'repairing');
        $this->device($room, 'D-3', 'retired');
        $this->device($room, 'D-4', 'disabled');

        $rows = $this->classroomRows();

        $this->assertSame('4', $rows['A101']['cells'][5]);
        $this->assertSame('3', $rows['A101']['cells'][6]);
    }

    // 連結：設備數 → 設備主檔（只看這間教室）；異常設備 → 設備主檔（只看這間教室的異常設備）；進行中工單 → 報修看板（只看這間教室）。
    public function test_counts_link_to_the_device_master_and_the_repair_board(): void
    {
        $room = $this->room('A101');
        $broken = $this->device($room, 'A-BROKEN', 'repairing');
        $this->repair($broken, 'pending');

        $html = $this->classroomRows()['A101']['html'];

        $this->assertStringContainsString(htmlspecialchars(route('devices.index', ['classroom_id' => $room->id])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('devices.index', ['classroom_id' => $room->id, 'abnormal' => 1])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['classroom_id' => $room->id])), $html);
    }

    // 核心設備異常（教室被標成設備異常）時，另外顯示「核心」徽章；非核心設備異常不顯示。
    public function test_core_badge_only_when_a_core_device_is_abnormal(): void
    {
        $core = $this->room('A101');
        $this->device($core, 'A-CORE', 'repairing', ['is_core' => true]);
        DeviceStatusService::syncClassroom($core);   // 重新計算教室的設備異常標記（正式流程是由設備主檔的新增／修改觸發）
        $plain = $this->room('B202');
        $this->device($plain, 'B-BROKEN', 'repairing');

        $rows = $this->classroomRows();

        $this->assertStringContainsString('核心', $rows['A101']['cells'][6]);
        $this->assertStringNotContainsString('核心', $rows['B202']['cells'][6]);
    }

    // 教室列表可以只看「有異常設備」的教室。
    public function test_classroom_list_can_filter_rooms_with_abnormal_devices(): void
    {
        $this->device($this->room('A101'), 'A-BROKEN', 'repairing');
        $this->device($this->room('B202'), 'B-OK');
        $this->room('C303');   // 完全沒有設備的教室

        $rows = $this->classroomRows(['abnormal' => 1]);

        $this->assertSame(['A101'], array_keys($rows));
        $this->assertCount(3, $this->classroomRows());
    }

    // 已停用（軟刪除）的設備不算在教室的設備數與異常數裡。
    public function test_soft_deleted_devices_are_not_counted(): void
    {
        $room = $this->room('A101');
        $device = $this->device($room, 'A-1', 'repairing');
        $this->patch(route('devices.disable', $device));

        $rows = $this->classroomRows();

        $this->assertSame('0', $rows['A101']['cells'][5]);
        $this->assertSame('0', $rows['A101']['cells'][6]);
    }

    // 設備主檔：可以只看異常設備、狀態徽章依異常程度上色、顯示每台設備進行中報修單數並連到報修看板。
    public function test_device_list_can_show_only_abnormal_devices_with_open_repair_counts(): void
    {
        $room = $this->room('A101');
        $this->device($room, 'DEV-OK');
        $broken = $this->device($room, 'DEV-BROKEN', 'repairing');
        $this->repair($broken, 'assigned');
        $this->repair($broken, 'completed');

        $this->get(route('devices.index', ['abnormal' => 1]))
            ->assertOk()->assertSee('DEV-BROKEN')->assertDontSee('DEV-OK')->assertSee('目前只顯示異常設備');

        $all = $this->get(route('devices.index'))->assertOk();
        $all->assertSee('DEV-OK')->assertSee('DEV-BROKEN');
        $all->assertSee(htmlspecialchars(route('repairs.index', ['device_id' => $broken->id])), false);
        $all->assertSee('text-bg-warning', false);   // 維修中 = 黃色徽章
        $all->assertSee('text-bg-success', false);   // 正常 = 綠色徽章
    }

    // 報修看板：可依教室篩選（只看該教室設備的報修單）；沒有綁定設備的報修單不會出現在教室篩選裡。
    public function test_repair_board_filters_by_classroom(): void
    {
        $a = $this->room('A101');
        $b = $this->room('B202');
        $this->repair($this->device($a, 'A-1'), 'pending', ['title' => 'A教室的設備壞了']);
        $this->repair($this->device($b, 'B-1'), 'pending', ['title' => 'B教室的設備壞了']);
        RepairRequest::factory()->create(['title' => '沒綁設備的報修', 'device_id' => null, 'status' => 'pending']);

        $this->get(route('repairs.index', ['classroom_id' => $a->id]))
            ->assertOk()->assertSee('A教室的設備壞了')->assertDontSee('B教室的設備壞了')->assertDontSee('沒綁設備的報修');
    }

    // 報修看板：可依設備篩選，並顯示「目前只顯示這台設備」的提醒；表格會在設備編號下面顯示所在教室。
    public function test_repair_board_filters_by_device_and_shows_the_classroom(): void
    {
        $room = $this->room('A101');
        $one = $this->device($room, 'DEV-ONE');
        $two = $this->device($room, 'DEV-TWO');
        $this->repair($one, 'pending', ['title' => '第一台壞了']);
        $this->repair($two, 'pending', ['title' => '第二台壞了']);

        $page = $this->get(route('repairs.index', ['device_id' => $one->id]));

        $page->assertOk()->assertSee('第一台壞了')->assertDontSee('第二台壞了');
        $page->assertSee('目前只顯示設備「DEV-ONE」的報修單');
        $page->assertSee('A101 A101 教室');
    }

    // 數字只算「這位用戶看得到的案件」：只有教室主檔權限、沒有派工權限的人，看不到別人的案件數。
    public function test_open_repair_counts_respect_case_visibility(): void
    {
        $room = $this->room('A101');
        $device = $this->device($room, 'A-1', 'repairing');
        $this->repair($device, 'pending');
        $this->repair($device, 'pending');

        $manager = $this->makeUserWithPermissions(['classrooms.manage']);
        $this->actingAs($manager);
        $this->assertSame('0', $this->classroomRows()['A101']['cells'][7]);

        $this->repair($device, 'pending', ['reporter_id' => $manager->id]);   // 自己報修的看得到
        $this->assertSame('1', $this->classroomRows()['A101']['cells'][7]);

        $this->actingAs($this->makeItManager());   // 能派工的人看全部
        $this->assertSame('3', $this->classroomRows()['A101']['cells'][7]);
    }

    // 主控台的「設備異常教室 N 間」用同一個定義：有異常設備的教室數（不只是核心設備異常的教室）。
    public function test_dashboard_counts_rooms_with_abnormal_devices(): void
    {
        $this->device($this->room('A101'), 'A-BROKEN', 'repairing');     // 非核心設備異常
        $this->device($this->room('B202'), 'B-RETIRED', 'retired');
        $this->device($this->room('C303'), 'C-OK');

        $this->get(route('dashboard'))->assertOk()->assertSee('設備異常教室 2 間');
    }
}
