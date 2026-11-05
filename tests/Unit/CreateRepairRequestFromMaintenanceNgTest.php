<?php

namespace Tests\Unit;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 這支測試是給王佑恩參考用的範例：確認「保養 NG 轉報修」這個接口確實可以正常運作、
 * 建出來的報修單欄位符合預期。
 */
class CreateRepairRequestFromMaintenanceNgTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;

    // 保養檢查 NG 轉報修：會建立一張「新報修」狀態的報修單，並在描述註明來源。
    public function test_creates_a_pending_repair_request_from_an_ng_result(): void
    {
        $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
            sourceLabel: 'maintenance_result:123',
            title: '投影機保養檢查 NG：燈泡亮度不足',
            description: '定期保養檢查時發現燈泡亮度低於標準值，建議更換。',
            deviceNote: 'A101 教室投影機',
            impactLevel: 'medium',
        );

        $this->assertSame('投影機保養檢查 NG：燈泡亮度不足', $repairRequest->title);
        $this->assertStringContainsString('maintenance_result:123', $repairRequest->description);
        $this->assertSame('medium', $repairRequest->impact_level);
        $this->assertFalse($repairRequest->is_class_affected);
        $this->assertSame('pending', $repairRequest->status->value);

        // 建出來的案件要能在一般的報修看板上看到，走一般報修單完全一樣的流程。
        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'device_note' => 'A101 教室投影機',
        ]);
    }

    // 輔助方法：建立一台設備（設備履歷用 repair_requests.device_id 找這台設備的報修單）。
    private function makeDevice(): Device
    {
        $category = DeviceCategory::create(['name' => '投影機']);
        $room = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1', 'classroom_code' => 'A101', 'name' => 'A101 教室']);

        return Device::create(['device_code' => 'DEV-A101-01', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
    }

    // 有帶 deviceId：建單當下就掛上設備關聯，報修單查得到、設備也查得到這張單。
    public function test_links_the_device_when_a_device_id_is_given(): void
    {
        $device = $this->makeDevice();

        $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
            sourceLabel: 'maintenance_result:124',
            title: '投影機保養檢查 NG',
            description: '風扇異音。',
            deviceNote: 'A101 教室投影機',
            impactLevel: 'medium',
            deviceId: $device->id,
        );

        $this->assertSame($device->id, $repairRequest->device_id);
        $this->assertDatabaseHas('repair_requests', ['id' => $repairRequest->id, 'device_id' => $device->id]);
        $this->assertTrue($device->repairRequests()->whereKey($repairRequest->id)->exists());
    }

    // 沒帶 deviceId：和以前一模一樣，device_id 是空的（向下相容）。
    public function test_device_id_stays_empty_when_no_device_id_is_given(): void
    {
        $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
            sourceLabel: 'maintenance_result:125',
            title: '投影機保養檢查 NG',
            description: '風扇異音。',
        );

        $this->assertNull($repairRequest->device_id);
        $this->assertDatabaseHas('repair_requests', ['id' => $repairRequest->id, 'device_id' => null]);
    }
}
