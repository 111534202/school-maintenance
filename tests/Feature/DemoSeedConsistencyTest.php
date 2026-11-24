<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 示範資料（php artisan db:seed）本身不能違反系統自己的規則，否則拿示範資料展示、再照規則操作時會前後矛盾。
 */
class DemoSeedConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_repair_logs_do_not_start_before_their_repair_was_requested(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, RepairLog::count());
        foreach (RepairLog::with('repairRequest')->get() as $log) {
            $requestedAt = $log->repairRequest->created_at->copy()->startOfMinute();
            $this->assertTrue(
                $log->started_at->greaterThanOrEqualTo($requestedAt),
                "維修紀錄 {$log->id} 的開始時間 {$log->started_at} 早於報修送出時間 {$requestedAt}"
            );
            $this->assertTrue($log->ended_at->greaterThanOrEqualTo($log->started_at), "維修紀錄 {$log->id} 結束早於開始");
            $this->assertTrue($log->ended_at->lessThanOrEqualTo(now()), "維修紀錄 {$log->id} 結束時間在未來");
        }
    }

    public function test_demo_parts_stock_equals_the_sum_of_batch_remaining_quantities(): void
    {
        $this->seed();

        foreach (Part::with('purchases')->get() as $part) {
            $this->assertSame((int) $part->purchases->sum('remaining_quantity'), (int) $part->current_stock, "零件 {$part->part_code} 的庫存和批次剩餘量加總不一致");
        }
        $this->assertSame(0, RepairPartUsage::whereDoesntHave('purchase')->count());
    }
}
