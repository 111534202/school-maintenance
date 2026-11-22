<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
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

        config(['app.locale' => 'zh_TW']);
        app()->setLocale('zh_TW');

        $this->loginAsAnyUser();
        $this->category = DeviceCategory::create(['name' => '投影機']);
    }

    private function room(string $code, array $attrs = []): Classroom
    {
        return Classroom::create(array_merge(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => $code, 'name' => "$code 教室"], $attrs));
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

    /** 「異常設備」＝有未結束報修單的設備：建一台設備並替它開一張報修單（預設新報修）。 */
    private function broken(Classroom $room, string $code, string $repairStatus = 'pending'): Device
    {
        $device = $this->device($room, $code);
        $this->repair($device, $repairStatus);

        return $device;
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
        $broken = $this->broken($a, 'A-BROKEN', 'assigned');
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
        $this->broken($this->room('A101'), 'A-BROKEN');
        $this->device($this->room('B202'), 'B-OK');

        $rows = $this->classroomRows();

        $this->assertStringContainsString('table-danger', $rows['A101']['class']);
        $this->assertStringNotContainsString('table-danger', $rows['B202']['class']);
    }

    // 「異常」＝有未結束的報修單（新報修、已派工、待驗收都算）；已結案、已退回不算；已淘汰的設備也不算。
    public function test_abnormal_means_the_device_has_an_open_repair_request(): void
    {
        $room = $this->room('A101');
        $this->device($room, 'D-1');                               // 沒有報修單
        $this->broken($room, 'D-2', 'pending');                    // 新報修：算
        $this->broken($room, 'D-3', 'pending_review');             // 待驗收：算
        $this->broken($room, 'D-4', 'completed');                  // 已結案：不算
        $this->broken($room, 'D-5', 'returned');                   // 已退回：不算
        $retired = $this->device($room, 'D-6', 'retired');         // 已淘汰：就算有報修單也不算
        $this->repair($retired, 'pending');

        $rows = $this->classroomRows();

        $this->assertSame('6', $rows['A101']['cells'][5]);
        $this->assertSame('2', $rows['A101']['cells'][6]);
    }

    // 連結：設備數 → 設備主檔（只看這間教室）；異常設備 → 設備主檔（只看這間教室的異常設備）；進行中工單 → 報修看板（只看這間教室）。
    public function test_counts_link_to_the_device_master_and_the_repair_board(): void
    {
        $room = $this->room('A101');
        $this->broken($room, 'A-BROKEN');

        $html = $this->classroomRows()['A101']['html'];

        $this->assertStringContainsString(htmlspecialchars(route('devices.index', ['classroom_id' => $room->id])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('devices.index', ['classroom_id' => $room->id, 'abnormal' => 1])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['device_classroom_id' => $room->id, 'open' => 1])), $html);
    }

    // 教室列表可以只看「有異常設備」的教室。
    public function test_classroom_list_can_filter_rooms_with_abnormal_devices(): void
    {
        $this->broken($this->room('A101'), 'A-BROKEN');
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
        $device = $this->broken($room, 'A-1');
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
        $broken = $this->broken($room, 'DEV-BROKEN', 'assigned');
        $this->repair($broken, 'completed');

        $this->get(route('devices.index', ['abnormal' => 1]))
            ->assertOk()->assertSee('DEV-BROKEN')->assertDontSee('DEV-OK')->assertSee('目前只顯示異常設備');

        $all = $this->get(route('devices.index'))->assertOk();
        $all->assertSee('DEV-OK')->assertSee('DEV-BROKEN');
        $all->assertSee(htmlspecialchars(route('repairs.index', ['device_id' => $broken->id, 'open' => 1])), false);   // 跳到看板：這台設備、未結案
        $all->assertSee('text-bg-warning', false)->assertSee('待維修');   // 有已派工的報修單 = 待維修（黃色徽章）
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

    // 報修看板：設備主檔連過來時用關鍵字（設備編號）篩選，關鍵字欄位直接帶入；表格會在設備編號下面顯示所在教室。
    public function test_repair_board_filters_by_device_and_shows_the_classroom(): void
    {
        $room = $this->room('A101');
        $one = $this->device($room, 'DEV-ONE');
        $two = $this->device($room, 'DEV-TWO');
        $this->repair($one, 'pending', ['title' => '第一台壞了']);
        $this->repair($two, 'pending', ['title' => '第二台壞了']);

        $page = $this->get(route('repairs.index', ['keyword' => 'DEV-ONE']));

        $page->assertOk()->assertSee('第一台壞了')->assertDontSee('第二台壞了');
        $page->assertSee('name="keyword" class="form-control form-control-sm" style="width: 15rem;"', false)->assertSee('value="DEV-ONE"', false)->assertDontSee('目前只顯示設備');
        $page->assertSee('A101 A101 教室');
    }

    // 數字只算「這位用戶看得到的案件」：只有教室主檔權限、沒有派工權限的人，看不到別人的案件數。
    public function test_open_repair_counts_respect_case_visibility(): void
    {
        $room = $this->room('A101');
        $device = $this->device($room, 'A-1', 'normal');
        $this->repair($device, 'pending');
        $this->repair($device, 'pending');

        $manager = $this->makeUserWithPermissions(['classrooms.manage']);
        $this->actingAs($manager);
        $this->assertSame('0', $this->classroomRows()['A101']['cells'][7]);

        $this->repair($device, 'pending', ['requester_id' => $manager->id]);   // 自己報修的看得到
        $this->assertSame('1', $this->classroomRows()['A101']['cells'][7]);

        $this->actingAs($this->makeItManager());   // 能派工的人看全部
        $this->assertSame('3', $this->classroomRows()['A101']['cells'][7]);
    }

    // 主控台的「設備異常教室 N 間」用同一個定義：有異常設備（未結束報修單）的教室數。
    public function test_dashboard_counts_rooms_with_abnormal_devices(): void
    {
        $this->broken($this->room('A101'), 'A-BROKEN');
        $this->broken($this->room('B202'), 'B-BROKEN', 'assigned');
        $this->device($this->room('C303'), 'C-RETIRED', 'retired');   // 已淘汰、沒有報修單：不算

        $this->get(route('dashboard'))->assertOk()->assertViewHas('classroomAbnormal', 2)->assertSee('設備異常教室')->assertSee(htmlspecialchars(route('classrooms.index', ['abnormal' => 1])), false);
    }
}
