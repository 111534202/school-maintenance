<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Models\Device;
use App\Services\MaintenanceCompletionRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

class MaintenanceCompletionRateServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMaintenanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    }

    public function test_it_calculates_completion_rate_within_the_date_range(): void
    {
        $service = app(MaintenanceCompletionRateService::class);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-10-01',
            'status' => MaintenanceOrder::STATUS_COMPLETED,
        ]);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-10-15',
            'status' => MaintenanceOrder::STATUS_COMPLETED,
        ]);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-10-20',
            'status' => MaintenanceOrder::STATUS_PENDING,
        ]);

        // 期間外工單不得納入分母。
        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-09-30',
            'status' => MaintenanceOrder::STATUS_PENDING,
        ]);

        $result = $service->forPeriod(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame('2026-10-01', $result['from']);
        $this->assertSame('2026-10-31', $result['to']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['completed']);
        $this->assertSame(0.6667, $result['rate']);
    }

    public function test_it_returns_null_rate_when_no_orders_match(): void
    {
        $service = app(MaintenanceCompletionRateService::class);

        $result = $service->forPeriod(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['completed']);
        $this->assertNull($result['rate']);
    }

    public function test_it_filters_orders_by_device_id(): void
    {
        $service = app(MaintenanceCompletionRateService::class);

        $deviceA = $this->makeDevice('T-RATE-001');
        $deviceB = $this->makeDevice('T-RATE-002');

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => $deviceA->id,
            'scheduled_date' => '2026-10-10',
            'status' => MaintenanceOrder::STATUS_COMPLETED,
        ]);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => $deviceA->id,
            'scheduled_date' => '2026-10-11',
            'status' => MaintenanceOrder::STATUS_PENDING,
        ]);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => $deviceB->id,
            'scheduled_date' => '2026-10-12',
            'status' => MaintenanceOrder::STATUS_PENDING,
        ]);

        $result = $service->forPeriod(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            $deviceA->id,
        );

        $this->assertSame($deviceA->id, $result['device_id']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(0.5, $result['rate']);
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $service = app(MaintenanceCompletionRateService::class);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-10-10',
            'status' => MaintenanceOrder::STATUS_COMPLETED,
        ]);

        MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => null,
            'device_id' => null,
            'scheduled_date' => '2026-09-30',
            'status' => MaintenanceOrder::STATUS_PENDING,
        ]);

        $result = $service->forPeriod();

        $this->assertSame('2026-10-01', $result['from']);
        $this->assertSame('2026-10-31', $result['to']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(1.0, $result['rate']);
    }
}