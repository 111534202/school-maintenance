<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Services\DueMaintenanceOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 6：保養主流程測試——計畫 → 工單、OK／NG 回報、NG → 報修。
 */
class MaintenanceFlowTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->actingAs($this->makeUser('admin'));
    }

    private function pendingOrder(): MaintenanceOrder
    {
        $device = $this->makeDevice();

        return MaintenanceOrder::create([
            'maintenance_plan_id' => $this->makePlan($device)->id,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => now()->toDateString(),
        ]);
    }

    // ---- 計畫 → 工單 ----

    public function test_creating_an_order_from_a_plan_copies_device_and_due_date(): void
    {
        $device = $this->makeDevice();
        $plan = $this->makePlan($device, ['next_due_date' => '2026-10-20']);

        $this->post(route('maintenance-plans.create-order', $plan))
            ->assertRedirect(route('maintenance-orders.index'));

        $order = MaintenanceOrder::firstOrFail();
        $this->assertSame($plan->id, $order->maintenance_plan_id);
        $this->assertSame($device->id, $order->device_id);
        $this->assertSame(MaintenanceOrder::SOURCE_PERIODIC, $order->source);
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->status);
        $this->assertSame('2026-10-20', $order->scheduled_date->toDateString());
    }

    public function test_inactive_plan_cannot_create_an_order(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['is_active' => false]);

        $this->post(route('maintenance-plans.create-order', $plan))->assertStatus(422);

        $this->assertSame(0, MaintenanceOrder::count());
    }

    public function test_due_order_generator_creates_one_order_per_due_date_only(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['next_due_date' => now()->subDay()->toDateString()]);
        $generator = app(DueMaintenanceOrderGenerator::class);

        $this->assertSame(1, $generator->generate());
        $this->assertSame(0, $generator->generate()); // 重跑不會疊加

        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());
    }

    // ---- OK / NG 回報 ----

    public function test_reporting_ok_completes_the_order(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ok',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-07 10:00:00',
        ])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);

        $result = MaintenanceResult::firstOrFail();
        $this->assertSame(MaintenanceResult::RESULT_OK, $result->result);
        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $result->ng_conversion_status);
    }

    public function test_reporting_ng_completes_the_order_and_hands_off_to_repair(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ng',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-07 10:00:00',
            'notes' => '風扇異音',
        ])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);

        // NG → 報修：彭仕衡的報修建立介面尚未併入前，NgToRepairService 只標記「待轉報修」。
        // 報修模組併入、串接後，這裡要改成斷言真的建立了報修單。
        $result = MaintenanceResult::firstOrFail();
        $this->assertSame(MaintenanceResult::RESULT_NG, $result->result);
        $this->assertSame(MaintenanceResult::NG_CONVERSION_PENDING, $result->ng_conversion_status);
        $this->assertSame('風扇異音', $result->notes);
    }

    public function test_result_requires_a_valid_result_value(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'maybe',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-07 10:00:00',
        ])->assertSessionHasErrors('result');

        $this->assertSame(0, MaintenanceResult::count());
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_order_cannot_be_reported_twice(): void
    {
        $order = $this->pendingOrder();
        $payload = ['result' => 'ok', 'executed_by' => '王小明', 'executed_at' => '2026-10-07 10:00:00'];

        $this->post(route('maintenance-orders.results.store', $order), $payload);
        $this->post(route('maintenance-orders.results.store', $order), $payload)->assertStatus(422);

        $this->assertSame(1, MaintenanceResult::count());
    }
}
