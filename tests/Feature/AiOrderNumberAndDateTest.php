<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * AI 預防保養產生的工單：編號一眼就能分辨（AI-00012），核准時由主管選排定保養日期（不再直接排定當天）。
 */
class AiOrderNumberAndDateTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function pendingCandidate(): PreventiveCandidate
    {
        $category = DeviceCategory::create(['name' => '投影機']);
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $device = Device::create(['device_code' => 'DEV-AI1', 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);

        return PreventiveCandidate::create([
            'device_id' => $device->id, 'risk_score' => 0.8, 'threshold' => 0.5, 'algorithm' => 'rule_based',
            'explanation' => [], 'features' => [], 'status' => PreventiveCandidate::STATUS_PENDING,
        ]);
    }

    public function test_the_order_number_shows_whether_it_is_ai_or_periodic(): void
    {
        $periodic = MaintenanceOrder::factory()->create(['source' => MaintenanceOrder::SOURCE_PERIODIC]);
        $ai = MaintenanceOrder::factory()->create(['source' => MaintenanceOrder::SOURCE_AI]);

        $this->assertSame('MO-' . str_pad((string) $periodic->id, 5, '0', STR_PAD_LEFT), $periodic->displayNumber());
        $this->assertSame('AI-' . str_pad((string) $ai->id, 5, '0', STR_PAD_LEFT), $ai->displayNumber());

        $this->actingAs($this->makeItManager());
        $this->get(route('maintenance-orders.index'))->assertSee($ai->displayNumber())->assertSee($periodic->displayNumber());
        $this->get(route('maintenance-orders.show', $ai))->assertSee($ai->displayNumber());
    }

    public function test_approving_uses_the_date_the_manager_picked(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($this->makeItManager());
        $date = now()->addDays(10)->toDateString();

        $this->post(route('preventive-candidates.approve', $candidate), ['scheduled_date' => $date])->assertRedirect()->assertSessionHas('success');

        $order = MaintenanceOrder::firstOrFail();
        $this->assertSame($date, $order->scheduled_date->toDateString());
        $this->assertSame(MaintenanceOrder::SOURCE_AI, $order->source);
        $this->assertStringContainsString($order->displayNumber(), session('success'));
    }

    public function test_a_past_date_is_refused_and_no_date_falls_back_to_today_plus_the_default_days(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($this->makeItManager());

        $this->post(route('preventive-candidates.approve', $candidate), ['scheduled_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('scheduled_date');
        $this->assertSame(0, MaintenanceOrder::count());

        AiSetting::put(AiSetting::SCHEDULE_LEAD_DAYS, 5);
        $this->post(route('preventive-candidates.approve', $candidate))->assertSessionHasNoErrors();
        $this->assertSame(now()->addDays(5)->toDateString(), MaintenanceOrder::firstOrFail()->scheduled_date->toDateString());
    }

    public function test_the_candidate_page_offers_a_date_field_prefilled_with_the_default(): void
    {
        $this->pendingCandidate();
        $this->actingAs($this->makeItManager());
        AiSetting::put(AiSetting::SCHEDULE_LEAD_DAYS, 4);

        $this->get(route('preventive-candidates.index'))->assertOk()
            ->assertSee('name="scheduled_date"', false)
            ->assertSee('value="' . now()->addDays(4)->toDateString() . '"', false);
    }

    public function test_the_lead_days_setting_can_be_changed(): void
    {
        $this->actingAs($this->makeItManager());

        $this->put(route('ai-settings.update'), [
            'risk_threshold' => 0.5, 'min_completed_orders' => 4, 'recent_window' => 6, 'dedup_window_days' => 14, 'require_approval' => 1, 'schedule_lead_days' => 9,
        ])->assertRedirect();

        $this->assertSame(9, AiSetting::get(AiSetting::SCHEDULE_LEAD_DAYS));
    }
}
