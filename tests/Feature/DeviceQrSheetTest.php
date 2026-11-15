<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 設備 QR 貼紙批次列印：依篩選條件輸出貼紙、只有設備主檔管理者能用、超過上限會提示。 */
class DeviceQrSheetTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code, ?Classroom $room = null, array $attrs = []): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);
        $room ??= Classroom::firstOrCreate(['classroom_code' => 'A101'], [
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal',
        ]);

        return Device::create($attrs + [
            'device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id,
            'brand' => 'Epson', 'model' => 'EB-X', 'status' => 'normal', 'is_core' => false,
        ]);
    }

    public function test_sheet_contains_a_qr_and_label_for_each_device(): void
    {
        $this->loginAsAnyUser();
        $this->device('DEV-0001');
        $this->device('DEV-0002');

        $response = $this->get(route('devices.qr-sheet'))->assertOk()->assertSee('DEV-0001')->assertSee('DEV-0002')->assertSee('Epson EB-X');
        $this->assertSame(2, substr_count($response->getContent(), '<svg'));   // 每台設備一個 QR（內嵌 SVG）
        $this->assertDatabaseHas('audit_logs', ['action' => 'downloaded']);
    }

    public function test_sheet_follows_the_same_filters_as_the_device_list(): void
    {
        $this->loginAsAnyUser();
        $other = Classroom::create(['campus' => '本校區', 'building' => 'B棟', 'floor' => '2F', 'classroom_code' => 'B201', 'name' => 'B201 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);
        $this->device('DEV-A');
        $this->device('DEV-B', $other);

        $this->get(route('devices.qr-sheet', ['classroom_id' => $other->id]))->assertOk()->assertSee('DEV-B')->assertDontSee('DEV-A');
        $this->get(route('devices.qr-sheet', ['keyword' => 'DEV-A']))->assertOk()->assertSee('DEV-A')->assertDontSee('DEV-B');
        $this->get(route('devices.qr-sheet', ['keyword' => 'nothing-matches']))->assertOk()->assertSee(__('devices.qr_sheet.empty'));
    }

    public function test_requires_the_devices_permission(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('devices.qr-sheet'))->assertForbidden();
    }

    public function test_warns_when_more_devices_than_one_batch(): void
    {
        $this->loginAsAnyUser();
        $category = DeviceCategory::create(['name' => '電腦']);
        $room = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);
        $rows = [];
        for ($i = 1; $i <= 245; $i++) {
            $rows[] = ['device_code' => sprintf('PC-%04d', $i), 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal', 'is_core' => false, 'created_at' => now(), 'updated_at' => now()];
        }
        Device::insert($rows);

        $response = $this->get(route('devices.qr-sheet'))->assertOk()->assertSee('一次最多印 240 張');
        $this->assertSame(240, substr_count($response->getContent(), '<div class="label">'));
    }

    public function test_device_list_has_the_print_button_carrying_current_filters(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('devices.index', ['status' => 'normal']))->assertOk()->assertSee(route('devices.qr-sheet', ['status' => 'normal']), false);
    }
}
