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
 * DeviceStatusService 的單元測試：設備狀態只能是規定的幾種（正常、已淘汰、停用；待維修由報修單推算），
 * 狀態改變才會寫紀錄。（核心設備與教室暫停預約的規則已拿掉。）
 */
class DeviceStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1']);
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

        DeviceStatusService::updateStatus($device, 'retired', '汰換');

        $this->assertSame('retired', $device->fresh()->status);
        $log = AuditLog::firstWhere('action', 'status_changed');
        $this->assertSame(['from' => 'normal', 'to' => 'retired'], $log->changes);
        $this->assertSame('汰換', $log->description);
    }

    // 「待維修」不能手動設定（由報修單推算），所以舊的 repairing 會被當成不合法的狀態擋下。
    public function test_awaiting_repair_cannot_be_stored_as_a_status(): void
    {
        $device = $this->makeDevice('D-1');

        $this->expectException(InvalidArgumentException::class);
        DeviceStatusService::updateStatus($device, 'repairing');
    }
}
