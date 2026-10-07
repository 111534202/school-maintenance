<?php

namespace Tests\Feature\Ai;

use App\Services\AI\PredictionServiceInterface;
use App\Services\AI\RuleBasedPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 1、6：規則式風險評分（可解釋、可重現）。
 *
 * 公式：0.5 × 最近 6 筆 NG 比例 + 0.3 × min(結尾連續 NG, 3)/3 + 0.2 × 逾期程度（45 天內 0、90 天以上 1）
 */
class RuleBasedPredictionServiceTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
    }

    public function test_service_is_bound_to_the_prediction_interface(): void
    {
        $this->assertInstanceOf(RuleBasedPredictionService::class, app(PredictionServiceInterface::class));
    }

    public function test_high_ng_history_scores_above_default_threshold(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn'); // 4/6 NG、結尾連續 3 次 NG

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        // 0.5 × (4/6) + 0.3 × 1 + 0.2 × 0 = 0.6333
        $this->assertEqualsWithDelta(0.6333, $prediction['risk_score'], 0.001);
        $this->assertGreaterThanOrEqual($prediction['threshold'], $prediction['risk_score']);
        $this->assertFalse($prediction['is_placeholder']);
        $this->assertFalse($prediction['insufficient_data']);
        $this->assertSame('rule_based_v1', $prediction['algorithm']);
    }

    public function test_explanation_lists_every_component_and_adds_up_to_the_score(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'oooonn');

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        $keys = array_column($prediction['explanation'], 'key');
        $this->assertSame(['recent_ng_rate', 'ng_streak', 'overdue'], $keys);

        // 0.5 × (2/6) + 0.3 × (2/3) + 0 = 0.3667
        $this->assertEqualsWithDelta(0.3667, $prediction['risk_score'], 0.001);
        $this->assertEqualsWithDelta(
            $prediction['risk_score'],
            array_sum(array_column($prediction['explanation'], 'contribution')),
            0.001
        );

        foreach ($prediction['explanation'] as $row) {
            $this->assertArrayHasKey('detail', $row);
            $this->assertNotSame('', $row['detail']);
        }
    }

    public function test_clean_history_is_low_risk(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'oooooo');

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        $this->assertEqualsWithDelta(0.0, $prediction['risk_score'], 0.001);
        $this->assertNull($prediction['recommended_action']);
    }

    public function test_history_with_too_few_records_is_not_scored(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'nnn'); // 只有 3 筆，低於預設最少 4 筆

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        $this->assertNull($prediction['risk_score']);
        $this->assertTrue($prediction['insufficient_data']);
        $this->assertSame([], $prediction['explanation']);
    }

    public function test_long_overdue_maintenance_adds_risk(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'oooo', lastDaysAgo: 100); // 距上次保養 100 天

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        $overdue = collect($prediction['explanation'])->firstWhere('key', 'overdue');
        $this->assertEqualsWithDelta(1.0, $overdue['raw'], 0.001);
        $this->assertEqualsWithDelta(0.2, $prediction['risk_score'], 0.001);
    }

    public function test_same_history_gives_the_same_result_every_time(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');

        $service = app(PredictionServiceInterface::class);

        $this->assertEquals($service->predict($device), $service->predict($device));
    }

    public function test_notes_state_that_repair_history_is_not_used_yet(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'oooo');

        $prediction = app(PredictionServiceInterface::class)->predict($device);

        $this->assertNotEmpty($prediction['notes']);
        $this->assertFalse($prediction['features']['repair_data_available']);
    }
}
