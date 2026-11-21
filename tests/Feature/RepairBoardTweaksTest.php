<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 維修案件看板與報修單畫面的調整：
 * - 排序：逾時的最上面（逾時越久越上面）→ 處理中的（新報修、已派工、待驗收）→ 已結束的（已結案、已退回），同一組依送出時間近→遠。
 * - 重複報修提醒放在標題下（紅色粗體、沒有圖示），點了用關鍵字（設備編號）篩選看板。
 * - 「設備／地點」改成「設備／發現地點」；修改報修單在上層對話框打開；附件點了在預覽視窗打開。
 * - 全站必填欄位：自動加紅色星號、漏填顯示「此欄位為必填」（共用 layouts/partials/required-fields）。
 */
class RepairBoardTweaksTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code): Device
    {
        $room = Classroom::firstOrCreate(['classroom_code' => 'A101'], ['campus' => '本', 'building' => 'A', 'floor' => '1', 'name' => 'A101 教室']);

        return Device::create(['device_code' => $code, 'device_category_id' => DeviceCategory::firstOrCreate(['name' => '投影機'])->id, 'classroom_id' => $room->id, 'status' => 'normal']);
    }

    public function test_board_sorts_overdue_first_then_open_then_closed_by_submitted_time(): void
    {
        $this->loginAsAnyUser();
        $make = fn (string $title, string $status, string $createdAt, ?string $scheduledAt = null) => RepairRequest::factory()->create([
            'title' => $title, 'status' => $status, 'created_at' => $createdAt, 'scheduled_at' => $scheduledAt, 'is_class_affected' => false,
        ]);
        $make('已結案新', 'completed', '2026-11-19 10:00', '2026-11-01 08:00');   // 已結案就算過了預計時間也不算逾時
        $make('已退回舊', 'returned', '2026-11-10 10:00');
        $make('處理中新', 'pending', '2026-11-18 10:00');
        $make('處理中舊', 'assigned', '2026-11-12 10:00', now()->addDays(3)->toDateTimeString());
        $make('逾時一天', 'assigned', '2026-11-17 10:00', now()->subDay()->toDateTimeString());
        RepairRequest::factory()->create(['title' => '逾時影響上課', 'status' => 'assigned', 'created_at' => '2026-11-16 10:00', 'scheduled_at' => now()->subHours(2)->toDateTimeString(), 'is_class_affected' => true]);
        $make('逾時五天', 'pending_review', '2026-11-11 10:00', now()->subDays(5)->toDateTimeString());

        $titles = $this->get(route('repairs.index'))->assertOk()->viewData('repairRequests')->pluck('title')->all();

        // 逾時的之間先排影響上課的，再依逾時多久。
        $this->assertSame(['逾時影響上課', '逾時五天', '逾時一天', '處理中新', '處理中舊', '已結案新', '已退回舊'], $titles);
    }

    public function test_repeat_repair_notice_sits_under_the_title_and_filters_by_keyword(): void
    {
        $this->loginAsAnyUser();
        $device = $this->device('DEV-REP');
        RepairRequest::factory()->count(2)->create(['device_id' => $device->id, 'title' => '投影機又壞了']);

        $html = $this->get(route('repairs.index'))->assertOk()->getContent();
        $link = htmlspecialchars(route('repairs.index', ['keyword' => 'DEV-REP']));
        $this->assertStringContainsString('<div><a href="' . $link . '" class="small text-danger fw-bold text-decoration-none"', $html);
        $this->assertStringContainsString('近30天報修2次</a>', $html);
        $this->assertStringNotContainsString('bi-arrow-repeat', $html);
        $this->assertStringContainsString('設備／發現地點', $html);
    }

    public function test_show_page_opens_edit_in_a_modal_and_attachments_in_the_preview_dialog(): void
    {
        $this->loginAsAnyUser();
        $repair = RepairRequest::factory()->create(['status' => 'pending', 'device_id' => $this->device('DEV-ATT')->id]);
        Attachment::create(['attachable_type' => RepairRequest::class, 'attachable_id' => $repair->id, 'file_name' => 'photo.jpg', 'file_path' => 'x/photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100]);

        $html = $this->get(route('repairs.show', $repair))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/href="' . preg_quote(route('repairs.edit', $repair), '/') . '" data-modal-header data-modal-frame/', $html);
        $this->assertStringContainsString('發現地點：', $html);
        $this->assertStringContainsString('data-bs-target="#attachmentModal"', $html);
        $this->assertStringContainsString('id="attachmentModal"', $html);
        $this->assertStringNotContainsString('target="_blank" title="photo.jpg"', $html);

        // 修改頁的發現地點可以輸入搜尋（和新增報修同一個選擇器）。
        $this->get(route('repairs.edit', $repair))->assertOk()->assertSee('id="classroom_search"', false)->assertSee('發現地點');
    }

    public function test_every_page_with_forms_gets_the_required_field_helper(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('repairs.create'))->assertOk()->assertSee('此欄位為必填')->assertSee('required-mark', false);
    }

    // 有附件的表單送出後被擋下（例如結束時間早於開始時間）：瀏覽器不能自動把檔案填回去，回到表單時提醒要重新選擇附件。
    public function test_a_failed_submit_with_attachments_warns_that_files_must_be_chosen_again(): void
    {
        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        $this->actingAs($technician);

        $this->from(route('repair-logs.create', $repair))->post(route('repair-logs.store', $repair), [
            'cause' => '壞了', 'resolution' => '修好',
            'started_at' => now()->subHour()->format('Y-m-d H:i'), 'ended_at' => now()->subHours(2)->format('Y-m-d H:i'),
            'attachments' => [\Illuminate\Http\UploadedFile::fake()->image('before.jpg')],
        ])->assertSessionHasErrors('ended_at')->assertSessionHas('uploads_discarded', true);

        $this->get(route('repair-logs.create', $repair))->assertOk()->assertSee('var uploadsDiscarded = true', false);
        $this->get(route('repair-logs.create', $repair))->assertSee('var uploadsDiscarded = false', false);   // 只提醒一次
    }

    // 報修時沒有設備（例如電燈壞了沒條碼）：可以先送出；維修人員填維修紀錄時一定要補選設備，送出後報修單記上這台設備。
    public function test_a_repair_without_device_needs_the_technician_to_pick_one_in_the_log(): void
    {
        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id, 'device_id' => null, 'device_note' => '走廊電燈']);
        $device = $this->device('LAMP-1');
        $this->actingAs($technician);

        $this->get(route('repair-logs.create', $repair))->assertOk()->assertSee('維修的設備')->assertSee('LAMP-1');
        $payload = ['cause' => '燈管老化', 'resolution' => '更換燈管', 'started_at' => now()->subHours(2)->format('Y-m-d H:i'), 'ended_at' => now()->subHour()->format('Y-m-d H:i')];

        $this->post(route('repair-logs.store', $repair), $payload)->assertSessionHasErrors('device_id');
        $this->assertSame('assigned', $repair->fresh()->status->value);

        $this->post(route('repair-logs.store', $repair), $payload + ['device_id' => $device->id])->assertSessionHasNoErrors();
        $this->assertSame($device->id, $repair->fresh()->device_id);
        $this->assertSame('pending_review', $repair->fresh()->status->value);

        // 已經有設備的報修單：表單不會出現設備選單。
        $other = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id, 'device_id' => $device->id]);
        $this->get(route('repair-logs.create', $other))->assertOk()->assertDontSee('id="device_search"', false);
    }

    // 已退回的報修單不算進設備履歷，也不算進「近 30 天報修 N 次」。
    public function test_returned_repairs_are_left_out_of_the_device_history_and_repeat_count(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $device = $this->device('DEV-RET');
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'returned', 'title' => '被退回的單']);
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'pending', 'title' => '還在處理的單']);

        $this->get(route('device-profile.show', $device))->assertOk()->assertSee('還在處理的單')->assertDontSee('被退回的單');
        $this->get(route('repairs.index'))->assertDontSee('近30天報修2次');
    }

    // 設備類別依筆畫排序（筆畫少的在前，英文排在中文後面）。
    public function test_device_categories_are_sorted_by_stroke_count(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        foreach (['螢幕', '筆電', 'Projector', '交換器', '一般設備', '冷氣'] as $name) {
            DeviceCategory::create(['name' => $name]);
        }

        $names = $this->get(route('device-categories.index'))->assertOk()->viewData('categories')->pluck('name')->all();
        $this->assertSame(['一般設備', '交換器', '冷氣', '筆電', '螢幕', 'Projector'], $names);
    }

    // 已停用（軟刪除）的設備：舊的保養工單改日期時查課表不會出現「No query results」錯誤。
    public function test_the_device_timetable_works_for_a_soft_deleted_device(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $device = $this->device('DEV-GONE');
        $device->delete();

        $this->getJson(route('devices.timetable', $device->id))->assertOk();
    }

    // 設備履歷的「零件使用成本」：列出維修紀錄登記的零件（同零件合併數量，金額只給零件庫存管理者看）；舊資料的文字使用備品說明不再顯示。
    public function test_device_profile_lists_parts_used_on_the_device(): void
    {
        $device = $this->device('DEV-PART');
        $repair = RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'assigned']);
        $log = $repair->repairLogs()->create(['cause' => '壞', 'resolution' => '修', 'parts_used_note' => '束線帶兩條', 'started_at' => now()->subHours(2), 'ended_at' => now()->subHour(), 'total_hours' => 1]);
        $part = \App\Models\Part::create(['part_code' => 'P-FAN', 'name' => '散熱風扇', 'unit' => '個', 'current_stock' => 0, 'safety_stock' => 0]);
        \App\Models\RepairPartUsage::create(['repair_log_id' => $log->id, 'part_id' => $part->id, 'quantity' => 2, 'unit_price' => 150]);

        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $this->get(route('device-profile.show', $device))->assertOk()
            ->assertSee('散熱風扇')->assertSee('2 個')->assertSee('零件使用成本')->assertSee('合計 300 元')->assertDontSee('束線帶兩條')->assertDontSee('備品耗用成本')->assertSee('報修單 #' . $repair->id);

        // 點維修紀錄那一列＝按「詳細」（對話框打開報修案件）；點零件那一列跳出它用在哪幾筆紀錄（可再點開那張報修單）。
        $html = $this->get(route('device-profile.show', $device))->getContent();
        $this->assertStringContainsString('<tr data-modal-frame-href="' . route('repairs.show', $repair) . '" data-modal-title="報修案件"', $html);
        $this->assertStringContainsString('class="part-usage-row"', $html);
        $this->assertStringContainsString(e(json_encode(route('repairs.show', $repair), JSON_UNESCAPED_SLASHES)), str_replace('\\/', '/', $html));
        $this->assertStringContainsString('300 元', $html);

        $viewer = $this->makeUserWithPermissions(['devices.view', 'maintenance.view', 'repairs.view']);
        $repair->update(['requester_id' => $viewer->id]);   // 報修人看得到自己的報修單
        $this->actingAs($viewer);
        $this->get(route('device-profile.show', $device))->assertOk()->assertSee('散熱風扇')->assertSee('使用零件')->assertSee('2 個')->assertSee('報修單 #' . $repair->id)
            ->assertDontSee('零件使用成本')->assertDontSee('合計 300 元')->assertDontSee('300 元');
    }
}
