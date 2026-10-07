<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use App\Services\AI\PreventiveCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 5 週任務 1、3：AI 工單（沒有來源計畫）走完整流程也不能壞，且來源要能追溯。
 */
class AiOrderLifecycleTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->actingAs($this->makeUser('admin'));
    }

    private function approvedAiOrder(string $code = 'T-AI'): MaintenanceOrder
    {
        $this->addHistory($this->makeDevice($code), 'ononnn');
        app(PreventiveCandidateService::class)->scan();

        return app(PreventiveCandidateService::class)->approve(PreventiveCandidate::firstOrFail());
    }

    public function test_ai_order_pages_render_without_a_source_plan(): void
    {
        $order = $this->approvedAiOrder();

        $this->get(route('maintenance-orders.index'))
            ->assertOk()
            ->assertSee('AI 辨識')
            ->assertSee('T-AI');

        $this->get(route('maintenance-orders.show', $order))
            ->assertOk()
            ->assertSee('AI 預防保養');

        $this->get(route('maintenance-orders.results.create', $order))->assertOk();
    }

    public function test_ai_order_can_be_completed_with_ok(): void
    {
        $order = $this->approvedAiOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ok',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-08 10:00:00',
        ])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->get(route('maintenance-orders.results.show', $order))->assertOk();
    }

    public function test_ai_order_reported_ng_is_handed_off_to_repair(): void
    {
        $order = $this->approvedAiOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ng',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-08 10:00:00',
        ]);

        // 報修模組（彭仕衡）已併入 → 真的轉成報修單；尚未併入 → 待轉報修。
        $result = MaintenanceResult::where('maintenance_order_id', $order->id)->firstOrFail();
        $this->assertSame(
            app(\App\Services\NgToRepairService::class)->repairModuleAvailable()
                ? MaintenanceResult::NG_CONVERSION_CONVERTED
                : MaintenanceResult::NG_CONVERSION_PENDING,
            $result->ng_conversion_status
        );
    }

    public function test_every_order_on_the_device_profile_shows_its_source_exactly_once(): void
    {
        $order = $this->approvedAiOrder('T-TRACE');
        $device = $order->device;

        $html = $this->get(route('device-profile.show', $device))->assertOk()->getContent();

        // 6 筆定期歷史 + 1 筆 AI 工單，各自只出現一列（沒有重複、沒有遺漏）。
        $this->assertSame(1, substr_count($html, 'badge text-bg-info">AI 辨識'));
        $this->assertSame(6, substr_count($html, 'badge text-bg-secondary">定期'));
        $this->assertSame(7, MaintenanceOrder::where('device_id', $device->id)->count());
    }

    public function test_order_list_links_each_order_to_its_device_profile(): void
    {
        $order = $this->approvedAiOrder('T-LINK');

        $this->get(route('maintenance-orders.index'))
            ->assertSee(route('device-profile.show', $order->device), false);
    }
}
