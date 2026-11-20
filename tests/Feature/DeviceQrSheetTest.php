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
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true,
        ]);

        return Device::create($attrs + [
            'device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id,
            'brand' => 'Epson', 'model' => 'EB-X', 'status' => 'normal',
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
        $other = Classroom::create(['campus' => '本校區', 'building' => 'B棟', 'floor' => '2F', 'classroom_code' => 'B201', 'name' => 'B201 教室', 'type' => '一般教室', 'is_active' => true]);
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
        $room = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $rows = [];
        for ($i = 1; $i <= 245; $i++) {
            $rows[] = ['device_code' => sprintf('PC-%04d', $i), 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal', 'created_at' => now(), 'updated_at' => now()];
        }
        Device::insert($rows);

        $response = $this->get(route('devices.qr-sheet'))->assertOk()->assertSee('一次最多印 240 張');
        $this->assertSame(240, substr_count($response->getContent(), '<div class="label">'));
    }

    // 設備列表的「列印 QR 貼紙」先開對話框：列出目前篩選到的設備讓人勾選（不選欄位），篩選條件一起送出，按「列印」在新分頁開貼紙頁。
    public function test_device_list_print_button_opens_a_selection_dialog_carrying_current_filters(): void
    {
        $this->loginAsAnyUser();
        $category = DeviceCategory::create(['name' => '投影機']);
        $room = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $device = Device::create(['device_code' => 'DEV-Q1', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);

        $html = $this->get(route('devices.index', ['status' => 'normal']))->assertOk()
            ->assertSee('data-bs-target="#deviceQrModal"', false)
            ->getContent();

        $this->assertSame(1, preg_match('#<div class="modal fade" id="deviceQrModal".*?</form>#s', $html, $m));
        $dialog = $m[0];
        $this->assertStringContainsString('action="' . route('devices.qr-sheet') . '"', $dialog);
        $this->assertStringContainsString('target="_blank"', $dialog);
        $this->assertStringContainsString('name="status" value="normal"', $dialog);
        $this->assertStringContainsString('name="ids[]" value="' . $device->id . '"', $dialog);
        $this->assertStringNotContainsString('name="columns[]"', $dialog);

        // 設備編號的連結也是開「設備詳細」對話框，不跳到整頁的設備詳細資料。
        $this->assertStringContainsString('href="' . route('devices.show', $device) . '" class="text-decoration-none" data-modal-frame', $html);
        $this->assertStringContainsString(__('devices.qr_sheet.print'), $dialog);
    }

    // 對話框勾選的設備（ids[]）：貼紙頁只印勾選的。
    public function test_sheet_prints_only_the_selected_devices(): void
    {
        $this->loginAsAnyUser();
        $category = DeviceCategory::create(['name' => '投影機']);
        $room = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $a = Device::create(['device_code' => 'DEV-SA', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
        Device::create(['device_code' => 'DEV-SB', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);

        $this->get(route('devices.qr-sheet', ['ids' => [$a->id]]))->assertOk()->assertSee('DEV-SA')->assertDontSee('DEV-SB');
    }
}
