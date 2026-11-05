<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

// 「重複報修」提醒的測試：同一台設備在 Device::REPEAT_REPAIR_DAYS 天內報修達到
// Device::REPEAT_REPAIR_THRESHOLD 次，就要在設備主檔與報修看板上標示出來。
class DeviceRepeatRepairTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private DeviceCategory $category;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('zh_TW');

        $this->loginAsAnyUser();
        $this->category = DeviceCategory::create(['name' => '投影機']);
        $this->classroom = Classroom::create([
            'campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A101', 'name' => 'A101 教室',
        ]);
    }

    private function device(string $code): Device
    {
        return Device::create([
            'device_code' => $code, 'device_category_id' => $this->category->id,
            'classroom_id' => $this->classroom->id, 'status' => 'normal',
        ]);
    }

    private function repair(Device $device, array $attrs = []): RepairRequest
    {
        return RepairRequest::factory()->create(array_merge(['device_id' => $device->id], $attrs));
    }

    // 近 30 天報修達到門檻（2 次）→ 設備主檔列表顯示重複報修提醒。
    public function test_device_list_flags_device_with_repeat_repairs(): void
    {
        $device = $this->device('DEV-REPEAT-1');
        $this->repair($device, ['created_at' => now()->subDays(5)]);
        $this->repair($device, ['created_at' => now()->subDays(2)]);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertSeeText('近30天報修2次');
    }

    // 只報修過一次，不算「重複」，不應該顯示提醒。
    public function test_device_list_does_not_flag_device_with_a_single_repair(): void
    {
        $device = $this->device('DEV-ONCE-1');
        $this->repair($device, ['created_at' => now()->subDays(5)]);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertDontSeeText('近30天報修');
    }

    // 30 天前的舊報修不算進視窗內，所以就算累積次數看起來多，只要都是舊的就不該被標記。
    public function test_repairs_older_than_the_window_do_not_count(): void
    {
        $device = $this->device('DEV-OLD-1');
        $this->repair($device, ['created_at' => now()->subDays(40)]);
        $this->repair($device, ['created_at' => now()->subDays(35)]);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertDontSeeText('近30天報修');
    }

    // 報修看板也要能看到同樣的提醒，不用切去設備主檔才查得到。
    public function test_repair_board_flags_rows_for_a_repeatedly_repaired_device(): void
    {
        $device = $this->device('DEV-REPEAT-2');
        $this->repair($device, ['title' => '第一次報修', 'created_at' => now()->subDays(10)]);
        $this->repair($device, ['title' => '第二次報修', 'created_at' => now()->subDay()]);

        $response = $this->get(route('repairs.index'));

        $response->assertOk();
        $response->assertSeeText('近30天報修2次');
    }
}
