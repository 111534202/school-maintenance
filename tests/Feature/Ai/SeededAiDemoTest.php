<?php

namespace Tests\Feature\Ai;

use App\Models\AiSetting;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use App\Services\AI\PredictionServiceInterface;
use App\Services\AI\PreventiveCandidateService;
use Database\Seeders\AiDemoDataSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 第 5 週任務 2：AI 結果校驗。
 *
 * 用正式的 DatabaseSeeder（等同 migrate:fresh --seed）建出展示資料，鎖定每個情境的預期輸出，
 * 確保「Demo 每次結果一致」。時間固定在 2026-10-07，避免「距上次保養幾天」隨日期漂移。
 *
 * 展示資料是模擬的保養歷史（不是學校真實多年資料），預期輸出如下：
 *   DEV-AI-001  ok ok ng ok ok ok   0.083  未達門檻
 *   DEV-AI-002  ok ng ok ng ng ng   0.633  達門檻 → 待審核候選
 *   DEV-AI-003  ok ok ok            資料不足
 *   DEV-AI-004  ok ok ok ng ng ng   0.550  達門檻，但已有 7 天後到期的定期工單 → 去重略過
 */
class SeededAiDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    private function device(string $code): Device
    {
        return Device::where('device_code', $code)->firstOrFail();
    }

    private function aiDevices(): array
    {
        return Device::whereIn('device_code', ['DEV-AI-001', 'DEV-AI-002', 'DEV-AI-003', 'DEV-AI-004'])->pluck('id')->all();
    }

    public function test_demo_devices_have_the_documented_risk_scores(): void
    {
        $predictor = app(PredictionServiceInterface::class);

        $this->assertEqualsWithDelta(0.0833, $predictor->predict($this->device('DEV-AI-001'))['risk_score'], 0.001);
        $this->assertEqualsWithDelta(0.6333, $predictor->predict($this->device('DEV-AI-002'))['risk_score'], 0.001);
        $this->assertNull($predictor->predict($this->device('DEV-AI-003'))['risk_score']);
        $this->assertEqualsWithDelta(0.55, $predictor->predict($this->device('DEV-AI-004'))['risk_score'], 0.001);
    }

    public function test_default_scan_proposes_exactly_one_candidate_among_demo_devices(): void
    {
        app(PreventiveCandidateService::class)->scan();

        $candidates = PreventiveCandidate::whereIn('device_id', $this->aiDevices())->get();

        $this->assertCount(1, $candidates);
        $this->assertSame($this->device('DEV-AI-002')->id, $candidates->first()->device_id);
        $this->assertSame(PreventiveCandidate::STATUS_PENDING, $candidates->first()->status);
    }

    public function test_the_high_risk_device_with_an_upcoming_periodic_order_is_deduplicated(): void
    {
        app(PreventiveCandidateService::class)->scan();

        $this->assertSame(0, PreventiveCandidate::where('device_id', $this->device('DEV-AI-004')->id)->count());
        $this->assertSame(0, MaintenanceOrder::where('device_id', $this->device('DEV-AI-004')->id)
            ->where('source', MaintenanceOrder::SOURCE_AI)->count());
    }

    public function test_scanning_repeatedly_gives_the_same_result(): void
    {
        $service = app(PreventiveCandidateService::class);

        $service->scan();
        $first = PreventiveCandidate::count();
        $service->scan();
        $service->scan();

        $this->assertSame($first, PreventiveCandidate::count());
    }

    public function test_reseeding_the_demo_data_does_not_duplicate_anything(): void
    {
        $orders = MaintenanceOrder::count();
        $devices = Device::count();

        $this->seed(AiDemoDataSeeder::class);

        $this->assertSame($orders, MaintenanceOrder::count());
        $this->assertSame($devices, Device::count());
    }

    public function test_direct_create_mode_builds_the_ai_order_without_review(): void
    {
        AiSetting::put(AiSetting::REQUIRE_APPROVAL, false);

        app(PreventiveCandidateService::class)->scan();

        $aiOrders = MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)
            ->whereIn('device_id', $this->aiDevices())->get();

        $this->assertCount(1, $aiOrders);
        $this->assertSame($this->device('DEV-AI-002')->id, $aiOrders->first()->device_id);
    }

    public function test_a_high_threshold_proposes_nothing_for_the_demo_devices(): void
    {
        AiSetting::put(AiSetting::RISK_THRESHOLD, 0.9);

        app(PreventiveCandidateService::class)->scan();

        $this->assertSame(0, PreventiveCandidate::whereIn('device_id', $this->aiDevices())->count());
    }

    public function test_a_low_threshold_still_never_proposes_insufficient_or_deduplicated_devices(): void
    {
        AiSetting::put(AiSetting::RISK_THRESHOLD, 0.05);

        app(PreventiveCandidateService::class)->scan();

        $proposed = PreventiveCandidate::pluck('device_id')->all();

        $this->assertContains($this->device('DEV-AI-001')->id, $proposed);
        $this->assertContains($this->device('DEV-AI-002')->id, $proposed);
        $this->assertNotContains($this->device('DEV-AI-003')->id, $proposed); // 資料不足
        $this->assertNotContains($this->device('DEV-AI-004')->id, $proposed); // 去重
    }
}
