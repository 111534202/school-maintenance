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
 * 保養 NG → 報修單：每台 NG 的設備各一張，來源可追溯、不重複建立（依《報修與保養流程修改說明》）。
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
            'device_category_id' => $device->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_CONVERTED,
            'scheduled_date' => now()->toDateString(),
        ]);

        return MaintenanceResult::create([
            'maintenance_order_id' => $order->id,
            'result' => MaintenanceResult::RESULT_NG,
            'executed_at' => now(),
            'note' => '風扇異音',
        ]);
    }

    public function test_ng_creates_one_traceable_repair_request_per_device_only_once(): void
    {
        $service = app(NgToRepairService::class);
        $result = $this->ngResult();
        $deviceId = $result->maintenanceOrder->device_id;

        $service->convert($result, [$deviceId]);
        $service->convert($result->fresh(), [$deviceId]); // 重複呼叫不可再建第二張

        $result->refresh();
        $this->assertSame(MaintenanceResult::NG_CONVERSION_CONVERTED, $result->ng_conversion_status);
        $this->assertSame(1, DB::table('repair_requests')->count());

        $repair = DB::table('repair_requests')->first();
        $this->assertSame($result->id, $repair->maintenance_result_id);
        $this->assertSame($repair->id, $result->repair_request_id);
        $this->assertSame('pending', $repair->status);
        $this->assertStringContainsString('【保養 NG 自動轉入，來源：保養工單 ' . $result->maintenanceOrder->displayNumber() . '】', $repair->description);
        $this->assertStringNotContainsString('maintenance_result:', $repair->description);
        $this->assertStringContainsString('風扇異音', $repair->description);
        $this->assertSame($deviceId, $repair->device_id);
    }

    public function test_ok_result_is_not_converted(): void
    {
        $result = $this->ngResult();
        $result->update(['result' => MaintenanceResult::RESULT_OK]);

        app(NgToRepairService::class)->convert($result->fresh(), []);

        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $result->fresh()->ng_conversion_status);
        $this->assertNull($result->fresh()->repair_request_id);
    }
}
