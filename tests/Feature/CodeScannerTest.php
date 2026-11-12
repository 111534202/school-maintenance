<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 相機掃描（QR／條碼）：新增報修與新增／編輯設備共用 scanner/_camera 元件；
 * 掃到的序號、資產編號、設備編號、QR 網址都要能直接讀取。
 * （掃得準不準由瀏覽器端的假鏡頭測試驗證，這裡只管頁面接線與後端查詢。）
 */
class CodeScannerTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'zh_TW']);
        app()->setLocale('zh_TW');

        $room = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A101', 'name' => 'A101 教室']);
        $category = DeviceCategory::create(['name' => '投影機']);
        $this->device = Device::create([
            'device_code' => 'DEV-1', 'asset_code' => 'AST-9001', 'serial_number' => 'SN-777',
            'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal',
        ]);
        $this->loginAsAnyUser();
    }

    public function test_repair_page_uses_the_shared_scanner(): void
    {
        $this->get(route('repairs.create'))->assertOk()
            ->assertSee('id="codeScannerModal"', false)
            ->assertSee('data-scan-target="#device_code_input"', false)
            ->assertDontSee('id="scanModal"', false)            // 舊的、自己一份的掃描視窗已拿掉
            ->assertDontSee('html5-qrcode', false);
    }

    public function test_new_and_edit_device_pages_have_a_scan_button_on_code_asset_and_serial(): void
    {
        foreach ([route('devices.create'), route('devices.edit', $this->device)] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="codeScannerModal"', false)
                ->assertSee('data-scan-target="#device_code"', false)
                ->assertSee('data-scan-extract="device-code"', false)   // 設備編號欄：掃到 QR 網址只取設備編號
                ->assertSee('data-scan-target="#asset_code"', false)
                ->assertSee('data-scan-target="#serial_number"', false);
            $this->assertSame(1, substr_count($this->get($url)->getContent(), 'id="codeScannerModal"'), '掃描視窗一頁只能有一份');
        }
    }

    public function test_scanner_tries_enlarged_frames_and_full_resolution(): void
    {
        // 靈敏度的關鍵設定寫在元件裡：高解析度、放大 2／3 倍再解碼、拍照備援、光學放大。
        $html = $this->get(route('repairs.create'))->getContent();
        $this->assertStringContainsString('DIGITAL_ZOOMS = [1, 2, 3]', $html);
        $this->assertStringContainsString('width: { ideal: 1920 }', $html);
        $this->assertStringContainsString('data-scanner-file', $html);
        $this->assertStringContainsString('data-scanner-zoom', $html);
    }

    public function test_lookup_reads_serial_number_asset_code_and_qr_url_and_returns_the_serial(): void
    {
        $this->getJson(route('repairs.device-lookup', ['code' => 'SN-777']))
            ->assertOk()->assertJson(['id' => $this->device->id, 'device_code' => 'DEV-1', 'serial_number' => 'SN-777']);
        $this->getJson(route('repairs.device-lookup', ['code' => 'AST-9001']))->assertOk()->assertJson(['id' => $this->device->id]);
        $this->getJson(route('repairs.device-lookup', ['code' => route('devices.entry', $this->device)]))->assertOk()->assertJson(['id' => $this->device->id]);
        // 條碼槍或掃描結果常帶換行與空白。
        $this->getJson(route('repairs.device-lookup', ['code' => "  SN-777\n"]))->assertOk()->assertJson(['id' => $this->device->id]);
    }
}
