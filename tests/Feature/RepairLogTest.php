<?php

namespace Tests\Feature;

use App\Models\RepairLog;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 維修紀錄（RepairLog）與報修單之間的資料關聯測試：雙向關聯、外鍵限制、連動刪除。
class RepairLogTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;

    // 維修紀錄與報修單的關聯是雙向的：紀錄知道自己屬於哪張單，單也能找到底下的紀錄。
    public function test_repair_log_belongs_to_a_repair_request(): void
    {
        $repairRequest = RepairRequest::factory()->create();
        $log = RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $this->assertTrue($log->repairRequest->is($repairRequest));
        $this->assertTrue($repairRequest->repairLogs->contains($log));
    }

    // 維修紀錄一定要指向存在的報修單（資料庫外鍵限制），亂指會失敗。
    public function test_repair_log_requires_a_valid_repair_request(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        RepairLog::factory()->create(['repair_request_id' => 999999]);
    }

    // 刪除報修單時，底下的維修紀錄會跟著一起刪除。
    public function test_deleting_repair_request_cascades_to_its_logs(): void
    {
        $repairRequest = RepairRequest::factory()->create();
        RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $repairRequest->delete();

        $this->assertDatabaseCount('repair_logs', 0);
    }
}
