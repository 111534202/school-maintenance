<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Services\DeviceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * DeviceStatusService 的單元測試：設備狀態只能是規定的幾種、狀態或核心旗標改變才會寫紀錄，
 * 以及「核心設備處於維修中／已淘汰／停用 → 所在教室標成設備異常」的計算規則。
 */
class DeviceStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'room_code' => 'A1', 'room_name' => 'A1']);
    }

    private function makeDevice(string $code, array $attrs = []): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);

        return Device::create(array_merge([
            'device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $this->room->id, 'status' => 'normal',
        ], $attrs));
    }

    // 狀態不在規定清單裡（亂傳）：直接丟例外，資料庫不會被寫入髒資料。
    public function test_unknown_status_is_rejected(): void
    {
        $device = $this->makeDevice('D-1');

        $this->expectException(InvalidArgumentException::class);
        try {
            DeviceStatusService::updateStatus($device, 'exploded');
        } finally {
            $this->assertSame('normal', $device->fresh()->status);
        }
    }

    // 狀態沒有改變：不寫入、不產生操作紀錄。
    public function test_unchanged_status_writes_nothing(): void
    {
        $device = $this->makeDevice('D-1');

        DeviceStatusService::updateStatus($device, 'normal');

        $this->assertSame(0, AuditLog::count());
    }

    // 狀態改變：寫進資料庫並留下操作紀錄（記錄從哪個狀態到哪個狀態，可帶原因當說明）。
    public function test_changed_status_is_saved_and_logged_with_the_reason(): void
    {
        $device = $this->makeDevice('D-1');

        DeviceStatusService::updateStatus($device, 'repairing', '送修中');

        $this->assertSame('repairing', $device->fresh()->status);
        $log = AuditLog::firstWhere('action', 'status_changed');
        $this->assertSame(['from' => 'normal', 'to' => 'repairing'], $log->changes);
        $this->assertSame('送修中', $log->description);
    }

    // 核心設備處於「維修中、已淘汰、停用」任一狀態，教室就是設備異常。
    public function test_a_core_device_in_any_problem_status_makes_the_classroom_abnormal(): void
    {
        foreach (['repairing', 'retired', 'disabled'] as $status) {
            $device = $this->makeDevice("D-$status", ['is_core' => true]);

            DeviceStatusService::updateStatus($device, $status);
            $this->assertSame('abnormal', $this->room->fresh()->reservation_status, "核心設備 {$status} 時教室應該是異常");

            DeviceStatusService::updateStatus($device, 'normal');
            $this->assertSame('normal', $this->room->fresh()->reservation_status, "修好之後教室應該回到正常（{$status}）");
        }
    }

    // 非核心設備不管壞成什麼樣子，都不影響教室。
    public function test_non_core_devices_never_affect_the_classroom(): void
    {
        $device = $this->makeDevice('D-1', ['is_core' => false]);

        DeviceStatusService::updateStatus($device, 'repairing');

        $this->assertSame('normal', $this->room->fresh()->reservation_status);
    }

    // 同一間教室有兩台壞掉的核心設備：修好其中一台，教室仍是異常；兩台都修好才回到正常。
    public function test_classroom_stays_abnormal_until_every_broken_core_device_is_fixed(): void
    {
        $a = $this->makeDevice('D-A', ['is_core' => true, 'status' => 'repairing']);
        $b = $this->makeDevice('D-B', ['is_core' => true, 'status' => 'repairing']);
        DeviceStatusService::syncClassroom($this->room);
        $this->assertSame('abnormal', $this->room->fresh()->reservation_status);

        DeviceStatusService::updateStatus($a, 'normal');
        $this->assertSame('abnormal', $this->room->fresh()->reservation_status);

        DeviceStatusService::updateStatus($b, 'normal');
        $this->assertSame('normal', $this->room->fresh()->reservation_status);
    }

    // 把壞掉的設備改成「核心設備」，教室才會變異常；取消核心則恢復；旗標沒變就不寫紀錄。
    public function test_core_flag_changes_are_logged_and_recalculate_the_classroom(): void
    {
        $device = $this->makeDevice('D-1', ['status' => 'repairing']);

        DeviceStatusService::setCore($device, true);
        $this->assertSame('abnormal', $this->room->fresh()->reservation_status);
        $this->assertSame(1, AuditLog::where('action', 'core_flag_changed')->count());

        DeviceStatusService::setCore($device, true);   // 沒有改變
        $this->assertSame(1, AuditLog::where('action', 'core_flag_changed')->count());

        DeviceStatusService::setCore($device, false);
        $this->assertSame('normal', $this->room->fresh()->reservation_status);
    }

    // 沒有所屬教室（空值）時不會出錯。
    public function test_sync_classroom_accepts_null(): void
    {
        DeviceStatusService::syncClassroom(null);

        $this->assertTrue(true);
    }
}
