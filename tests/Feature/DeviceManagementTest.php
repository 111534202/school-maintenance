<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 設備主檔：權限、新增／修改／停用、驗證、篩選、QR Code 與設備入口頁，
 * 以及「核心設備異常 → 教室設備異常標記」的連動（由 DeviceStatusService 維護）。
 */
class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private Classroom $room;
    private DeviceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
        $this->room = $this->makeClassroom('A101');
        $this->category = DeviceCategory::create(['name' => '投影機']);
    }

    private function makeClassroom(string $code): Classroom
    {
        return Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'room_code' => $code, 'room_name' => "$code 教室"]);
    }

    private function makeDevice(array $attrs = []): Device
    {
        return Device::create(array_merge([
            'device_code' => 'DEV-1', 'device_category_id' => $this->category->id, 'classroom_id' => $this->room->id, 'status' => 'normal',
        ], $attrs));
    }

    /** 輔助方法：一份完全合法的設備表單內容。 */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'device_code' => 'DEV-NEW', 'device_category_id' => $this->category->id, 'classroom_id' => $this->room->id,
            'status' => 'normal', 'brand' => 'Epson', 'model' => 'EB-1',
        ], $overrides);
    }

    // 只有被勾選「設備主檔」權限的人進得去：維修人員 403，資訊組主管（預設有勾）可以。
    public function test_only_users_with_devices_manage_permission_can_open_it(): void
    {
        $this->actingAs($this->makeTechnician());
        $this->get(route('devices.index'))->assertForbidden();
        $this->post(route('devices.store'), $this->payload())->assertForbidden();
        $this->get(route('devices.qrcode', $this->makeDevice()))->assertForbidden();

        $this->actingAs($this->makeItManager());
        $this->get(route('devices.index'))->assertOk();
    }

    public function test_admin_can_create_a_device_and_it_is_logged(): void
    {
        $this->post(route('devices.store'), $this->payload(['asset_code' => 'AS-1', 'warranty_until' => '2030-01-31']))
            ->assertRedirect(route('devices.index'))->assertSessionHas('success');

        $device = Device::firstWhere('device_code', 'DEV-NEW');
        $this->assertSame('AS-1', $device->asset_code);
        $this->assertSame('2030-01-31', $device->warranty_until->toDateString());
        $this->assertFalse($device->is_core);   // 核取方塊沒勾 = 不是核心設備
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $device->id, 'loggable_type' => Device::class]);
    }

    // 驗證：設備編號必填且不可重複、類別與教室必須存在、狀態只能是規定的幾種、保固期限要是日期。
    public function test_create_validates_required_unique_and_allowed_values(): void
    {
        $this->makeDevice(['device_code' => 'DEV-1']);

        $this->post(route('devices.store'), $this->payload(['device_code' => 'DEV-1']))->assertSessionHasErrors('device_code');
        $this->post(route('devices.store'), $this->payload(['device_code' => '']))->assertSessionHasErrors('device_code');
        $this->post(route('devices.store'), $this->payload(['device_category_id' => 99999]))->assertSessionHasErrors('device_category_id');
        $this->post(route('devices.store'), $this->payload(['classroom_id' => 99999]))->assertSessionHasErrors('classroom_id');
        $this->post(route('devices.store'), $this->payload(['status' => 'exploded']))->assertSessionHasErrors('status');
        $this->post(route('devices.store'), $this->payload(['warranty_until' => 'not-a-date']))->assertSessionHasErrors('warranty_until');

        $this->assertSame(1, Device::count());
    }

    // 新增一台「核心設備、維修中」的設備，所在教室立刻被標成設備異常。
    public function test_creating_a_broken_core_device_marks_its_classroom_abnormal(): void
    {
        $this->post(route('devices.store'), $this->payload(['is_core' => '1', 'status' => 'repairing']));

        $this->assertSame('abnormal', $this->room->fresh()->reservation_status);
    }

    // 非核心設備壞掉，不影響教室的異常標記。
    public function test_a_broken_non_core_device_does_not_mark_the_classroom(): void
    {
        $this->post(route('devices.store'), $this->payload(['status' => 'repairing']));

        $this->assertSame('normal', $this->room->fresh()->reservation_status);
    }

    // 修改設備的狀態會寫進操作紀錄（from/to），並重新計算教室異常標記；修好之後教室回到正常。
    public function test_updating_status_is_logged_and_the_classroom_flag_follows(): void
    {
        $device = $this->makeDevice(['is_core' => true]);

        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-1', 'is_core' => '1', 'status' => 'repairing']))
            ->assertRedirect(route('devices.index'));
        $this->assertSame('repairing', $device->fresh()->status);
        $this->assertSame('abnormal', $this->room->fresh()->reservation_status);
        $log = AuditLog::where('action', 'status_changed')->where('loggable_id', $device->id)->latest('id')->first();
        $this->assertSame(['from' => 'normal', 'to' => 'repairing'], $log->changes);

        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-1', 'is_core' => '1', 'status' => 'normal']));
        $this->assertSame('normal', $this->room->fresh()->reservation_status);
    }

    // 取消「核心設備」勾選也會重新計算教室標記，並寫進操作紀錄。
    public function test_unticking_core_recalculates_the_classroom_and_is_logged(): void
    {
        $device = $this->makeDevice(['is_core' => true, 'status' => 'repairing']);
        $this->room->update(['reservation_status' => 'abnormal']);

        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-1', 'status' => 'repairing']));

        $this->assertFalse($device->fresh()->is_core);
        $this->assertSame('normal', $this->room->fresh()->reservation_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'core_flag_changed', 'loggable_id' => $device->id]);
    }

    // 把壞掉的核心設備搬到另一間教室：舊教室恢復正常，新教室變成異常。
    public function test_moving_a_broken_core_device_updates_both_classrooms(): void
    {
        $other = $this->makeClassroom('B202');
        $device = $this->makeDevice(['is_core' => true, 'status' => 'repairing']);
        $this->room->update(['reservation_status' => 'abnormal']);

        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-1', 'is_core' => '1', 'status' => 'repairing', 'classroom_id' => $other->id]));

        $this->assertSame('normal', $this->room->fresh()->reservation_status);
        $this->assertSame('abnormal', $other->fresh()->reservation_status);
    }

    public function test_update_keeps_own_device_code_but_cannot_take_another(): void
    {
        $device = $this->makeDevice(['device_code' => 'DEV-1']);
        $this->makeDevice(['device_code' => 'DEV-2']);

        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-1']))->assertSessionHasNoErrors();
        $this->put(route('devices.update', $device), $this->payload(['device_code' => 'DEV-2']))->assertSessionHasErrors('device_code');
    }

    // 停用設備：狀態變成 disabled 並軟刪除（資料保留、列表看不到），所在教室重新計算標記。
    public function test_disable_soft_deletes_the_device_and_recalculates_the_classroom(): void
    {
        $device = $this->makeDevice(['is_core' => true, 'status' => 'repairing']);
        $this->room->update(['reservation_status' => 'abnormal']);

        $this->patch(route('devices.disable', $device))->assertRedirect(route('devices.index'));

        $this->assertSoftDeleted('devices', ['id' => $device->id]);
        $this->assertSame('disabled', Device::withTrashed()->find($device->id)->status);
        $this->get(route('devices.index'))->assertDontSee('DEV-1');
        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'loggable_id' => $device->id]);
    }

    public function test_index_can_filter_by_keyword_classroom_category_and_status(): void
    {
        $other = $this->makeClassroom('B202');
        $screen = DeviceCategory::create(['name' => '螢幕']);
        $this->makeDevice(['device_code' => 'PRJ-001', 'brand' => 'Epson', 'status' => 'normal']);
        $this->makeDevice(['device_code' => 'SCR-001', 'device_category_id' => $screen->id, 'classroom_id' => $other->id, 'status' => 'repairing']);

        $this->get(route('devices.index', ['keyword' => 'Epson']))->assertSee('PRJ-001')->assertDontSee('SCR-001');
        $this->get(route('devices.index', ['classroom_id' => $other->id]))->assertSee('SCR-001')->assertDontSee('PRJ-001');
        $this->get(route('devices.index', ['device_category_id' => $screen->id]))->assertSee('SCR-001')->assertDontSee('PRJ-001');
        $this->get(route('devices.index', ['status' => 'repairing']))->assertSee('SCR-001')->assertDontSee('PRJ-001');
    }

    // QR Code 回傳 SVG 圖片，內容編碼的是設備入口頁的固定網址（只含設備編號，不寫死教室等會變動的資料）。
    public function test_qrcode_is_an_svg_pointing_to_the_entry_url(): void
    {
        $device = $this->makeDevice(['device_code' => 'DEV-QR']);

        $response = $this->get(route('devices.qrcode', $device));

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertSame('/d/DEV-QR', parse_url(route('devices.entry', $device), PHP_URL_PATH));
    }

    // 設備詳細頁與入口頁都能正常開啟並顯示中文狀態（任何登入者都能開入口頁）。
    public function test_show_and_entry_pages_render_with_the_chinese_status(): void
    {
        $device = $this->makeDevice(['device_code' => 'DEV-1', 'status' => 'repairing']);

        $this->get(route('devices.show', $device))->assertOk()->assertSee('DEV-1')->assertSee('維修中');

        $this->actingAs($this->makeTechnician());
        $this->get(route('devices.entry', $device))->assertOk()->assertSee('維修中')->assertSee('前往報修');
    }

    // 停用（軟刪除）後的設備，入口頁網址找不到（404）。
    public function test_entry_page_of_a_disabled_device_is_not_found(): void
    {
        $device = $this->makeDevice(['device_code' => 'DEV-GONE']);
        $device->delete();

        $this->get('/d/DEV-GONE')->assertNotFound();
    }
}
