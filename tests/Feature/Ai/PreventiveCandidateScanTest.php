<?php

namespace Tests\Feature\Ai;

use App\Models\AiSetting;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use App\Services\AI\PreventiveCandidateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 2、3、4、6：AI 觸發／不觸發、候選審核、去重。
 */
class PreventiveCandidateScanTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
    }

    private function service(): PreventiveCandidateService
    {
        return app(PreventiveCandidateService::class);
    }

    // ---- AI 觸發／不觸發 ----

    public function test_high_risk_device_gets_a_pending_candidate_but_no_order(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['candidates_created']);
        $this->assertSame(0, $summary['orders_created']);

        $candidate = PreventiveCandidate::firstOrFail();
        $this->assertSame($device->id, $candidate->device_id);
        $this->assertSame(PreventiveCandidate::STATUS_PENDING, $candidate->status);
        $this->assertEqualsWithDelta(0.6333, $candidate->risk_score, 0.001);
        $this->assertCount(3, $candidate->explanation);

        $this->assertSame(0, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
    }

    public function test_low_risk_device_does_not_trigger(): void
    {
        $this->addHistory($this->makeDevice(), 'oooooo');

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['skipped_below_threshold']);
        $this->assertSame(0, PreventiveCandidate::count());
    }

    public function test_device_with_insufficient_history_does_not_trigger(): void
    {
        $this->addHistory($this->makeDevice(), 'nnn');

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['skipped_insufficient_data']);
        $this->assertSame(0, PreventiveCandidate::count());
    }

    public function test_raising_the_threshold_stops_the_trigger(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn'); // 0.6333
        AiSetting::put(AiSetting::RISK_THRESHOLD, 0.9);

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['skipped_below_threshold']);
        $this->assertSame(0, PreventiveCandidate::count());
    }

    public function test_only_normal_status_devices_are_scanned(): void
    {
        $device = $this->makeDevice('T-REPAIR', 'repairing');
        $this->addHistory($device, 'ononnn');

        $summary = $this->service()->scan();

        $this->assertSame(0, $summary['scanned']);
        $this->assertSame(0, PreventiveCandidate::count());
    }

    public function test_artisan_command_runs_the_scan(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');

        $this->artisan('ai:scan-preventive')->assertExitCode(0);

        $this->assertSame(1, PreventiveCandidate::count());
    }

    public function test_no_approval_setting_creates_an_ai_order_directly(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        AiSetting::put(AiSetting::REQUIRE_APPROVAL, false);

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['orders_created']);
        $this->assertSame(0, $summary['candidates_created']);

        $order = MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->firstOrFail();
        $this->assertSame($device->id, $order->device_id);
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->status);

        $candidate = PreventiveCandidate::firstOrFail();
        $this->assertSame(PreventiveCandidate::STATUS_AUTO_CREATED, $candidate->status);
        $this->assertSame($order->id, $candidate->maintenance_order_id);
    }

    // ---- 核准／駁回 ----

    public function test_approving_a_candidate_creates_one_ai_source_order(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->service()->scan();
        $candidate = PreventiveCandidate::firstOrFail();
        $manager = $this->makeUser('it_manager');

        $order = $this->service()->approve($candidate, $manager, '同意提前保養');

        $this->assertNotNull($order);
        $this->assertSame(MaintenanceOrder::SOURCE_AI, $order->source);
        $this->assertSame($device->id, $order->device_id);
        $this->assertNull($order->maintenance_plan_id);

        $candidate->refresh();
        $this->assertSame(PreventiveCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertSame($order->id, $candidate->maintenance_order_id);
        $this->assertSame($manager->id, $candidate->decided_by);
    }

    public function test_a_candidate_cannot_be_approved_twice(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');
        $this->service()->scan();
        $candidate = PreventiveCandidate::firstOrFail();

        $this->service()->approve($candidate);

        $this->expectException(DomainException::class);
        $this->service()->approve($candidate);
    }

    public function test_rejecting_a_candidate_creates_no_order(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');
        $this->service()->scan();
        $candidate = PreventiveCandidate::firstOrFail();

        $this->service()->reject($candidate, $this->makeUser('admin'), '先觀察');

        $candidate->refresh();
        $this->assertSame(PreventiveCandidate::STATUS_REJECTED, $candidate->status);
        $this->assertNull($candidate->maintenance_order_id);
        $this->assertSame(0, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
    }

    // ---- 去重 ----

    public function test_scanning_twice_does_not_create_a_second_candidate(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');

        $this->service()->scan();
        $second = $this->service()->scan();

        $this->assertSame(1, PreventiveCandidate::count());
        $this->assertSame(1, $second['skipped_duplicate']);
    }

    public function test_open_periodic_order_due_soon_blocks_a_new_candidate(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->makeOpenOrder($device, dueInDays: 7); // 7 天後到期，在預設 14 天去重視窗內

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['skipped_duplicate']);
        $this->assertSame(0, PreventiveCandidate::count());
    }

    public function test_open_order_due_far_in_the_future_does_not_block(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->makeOpenOrder($device, dueInDays: 60); // 超出去重視窗

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['candidates_created']);
    }

    public function test_dedup_window_is_adjustable(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->makeOpenOrder($device, dueInDays: 30);

        AiSetting::put(AiSetting::DEDUP_WINDOW_DAYS, 45); // 視窗拉大到 45 天，30 天後到期的工單也算重複

        $summary = $this->service()->scan();

        $this->assertSame(1, $summary['skipped_duplicate']);
    }

    public function test_approval_is_blocked_if_a_periodic_order_appeared_after_the_candidate(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->service()->scan();
        $candidate = PreventiveCandidate::firstOrFail();

        $this->makeOpenOrder($device, dueInDays: 3); // 候選產生後，有人建立了即將到期的定期工單

        $order = $this->service()->approve($candidate);

        $this->assertNull($order);
        $this->assertSame(PreventiveCandidate::STATUS_DUPLICATE, $candidate->fresh()->status);
        $this->assertSame(0, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
    }

    public function test_recently_created_ai_order_blocks_another_candidate_until_the_window_passes(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        $this->service()->scan();
        $order = $this->service()->approve(PreventiveCandidate::firstOrFail());
        $order->update(['status' => MaintenanceOrder::STATUS_COMPLETED]); // 工單做完了，不再是 open

        $blocked = $this->service()->scan();
        $this->assertSame(1, $blocked['skipped_duplicate']);
        $this->assertSame(1, PreventiveCandidate::count());

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00')->addDays(15)); // 超過 14 天視窗
        $allowed = $this->service()->scan();
        $this->assertSame(1, $allowed['candidates_created']);
        $this->assertSame(2, PreventiveCandidate::count());
    }

    public function test_recently_rejected_device_is_not_proposed_again_within_the_window(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');
        $this->service()->scan();
        $this->service()->reject(PreventiveCandidate::firstOrFail());

        $blocked = $this->service()->scan();
        $this->assertSame(1, $blocked['skipped_duplicate']);
        $this->assertSame(1, PreventiveCandidate::count());

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00')->addDays(15));
        $allowed = $this->service()->scan();
        $this->assertSame(1, $allowed['candidates_created']);
    }
}
