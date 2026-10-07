<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Services\NgToRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 5 週任務 1：保養 NG → 報修單，來源可追溯、不重複建立。
 *
 * 報修模組（彭仕衡）尚未併入時走「待轉報修」分支；併入後走「真的建立報修單」分支。
 * 兩個分支各自在不適用時 skip，所以兩種狀態下測試都應全綠（skip 不算失敗）。
 */
class NgToRepairServiceTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    private function ngResult(): MaintenanceResult
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));
        $device = $this->makeDevice();
        $order = MaintenanceOrder::create([
            'maintenance_plan_id' => $this->makePlan($device)->id,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_COMPLETED,
            'scheduled_date' => now()->toDateString(),
        ]);

        return MaintenanceResult::create([
            'maintenance_order_id' => $order->id,
            'result' => MaintenanceResult::RESULT_NG,
            'executed_by' => '王小明',
            'executed_at' => now(),
            'notes' => '風扇異音',
        ]);
    }

    public function test_ng_stays_pending_handoff_until_repair_module_is_merged(): void
    {
        $service = app(NgToRepairService::class);
        if ($service->repairModuleAvailable()) {
            $this->markTestSkipped('報修模組已併入，改由 converted 分支測試。');
        }

        $result = $this->ngResult();
        $service->convert($result);

        $this->assertSame(MaintenanceResult::NG_CONVERSION_PENDING, $result->fresh()->ng_conversion_status);
        $this->assertNull($result->fresh()->repair_request_id);
    }

    public function test_ng_creates_one_traceable_repair_request(): void
    {
        $service = app(NgToRepairService::class);
        if (! $service->repairModuleAvailable()) {
            $this->markTestSkipped('報修模組尚未併入 develop，併入後此測試自動生效。');
        }

        $result = $this->ngResult();
        $service->convert($result);
        $service->convert($result->fresh()); // 重複呼叫不可再建第二張

        $result->refresh();
        $this->assertSame(MaintenanceResult::NG_CONVERSION_CONVERTED, $result->ng_conversion_status);
        $this->assertNotNull($result->repair_request_id);
        $this->assertSame(1, DB::table('repair_requests')->count());

        $repair = DB::table('repair_requests')->find($result->repair_request_id);
        $this->assertSame('pending', $repair->status);
        $this->assertStringContainsString('maintenance_result:'.$result->id, $repair->description);
        $this->assertStringContainsString('風扇異音', $repair->description);
        $this->assertSame($result->maintenanceOrder->device_id, $repair->device_id);
    }

    public function test_ok_result_is_not_converted(): void
    {
        $result = $this->ngResult();
        $result->update(['result' => MaintenanceResult::RESULT_OK]);

        app(NgToRepairService::class)->convert($result->fresh());

        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $result->fresh()->ng_conversion_status);
        $this->assertNull($result->fresh()->repair_request_id);
    }
}
