<?php

namespace Tests\Unit;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
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
        $this->assertFalse($repairRequest->affects_class);
        $this->assertSame('pending', $repairRequest->status->value);

        // 建出來的案件要能在一般的報修看板上看到，走一般報修單完全一樣的流程。
        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'device_note' => 'A101 教室投影機',
        ]);
    }
}
