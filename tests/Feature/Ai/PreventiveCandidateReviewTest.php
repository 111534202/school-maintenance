<?php

namespace Tests\Feature\Ai;

use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use App\Services\AI\PreventiveCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 3、6：主管審核頁與參數設定頁（含權限）。
 */
class PreventiveCandidateReviewTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
    }

    private function pendingCandidate(string $code = 'T-001'): PreventiveCandidate
    {
        $this->addHistory($this->makeDevice($code), 'ononnn');
        app(PreventiveCandidateService::class)->scan();

        return PreventiveCandidate::firstOrFail();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('preventive-candidates.index'))->assertRedirect(route('login'));
    }

    public function test_technician_cannot_open_the_review_page_or_settings(): void
    {
        $this->actingAs($this->makeUser('technician'));

        $this->get(route('preventive-candidates.index'))->assertForbidden();
        $this->get(route('ai-settings.edit'))->assertForbidden();
    }

    public function test_technician_cannot_approve_or_change_settings(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($this->makeUser('technician'));

        $this->post(route('preventive-candidates.approve', $candidate))->assertForbidden();
        $this->put(route('ai-settings.update'), [])->assertForbidden();

        $this->assertSame(PreventiveCandidate::STATUS_PENDING, $candidate->fresh()->status);
    }

    public function test_manager_sees_the_pending_candidate_with_its_score(): void
    {
        $this->pendingCandidate('T-SHOW');
        $this->actingAs($this->makeUser('it_manager'));

        $this->get(route('preventive-candidates.index'))
            ->assertOk()
            ->assertSee('T-SHOW')
            ->assertSee('0.633')
            ->assertSee('待審核');
    }

    public function test_manager_can_approve_and_an_ai_order_is_created(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($manager = $this->makeUser('it_manager'));

        $this->post(route('preventive-candidates.approve', $candidate), ['decision_note' => 'OK'])
            ->assertRedirect(route('preventive-candidates.index'))
            ->assertSessionHas('success');

        $candidate->refresh();
        $this->assertSame(PreventiveCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertSame($manager->id, $candidate->decided_by);
        $this->assertSame(1, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
        $this->assertTrue(AuditLog::where('action', 'ai_candidate_approved')->exists());
    }

    public function test_manager_can_reject_without_creating_an_order(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($this->makeUser('admin'));

        $this->post(route('preventive-candidates.reject', $candidate), ['decision_note' => '先觀察'])
            ->assertRedirect(route('preventive-candidates.index'));

        $this->assertSame(PreventiveCandidate::STATUS_REJECTED, $candidate->fresh()->status);
        $this->assertSame(0, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
        $this->assertTrue(AuditLog::where('action', 'ai_candidate_rejected')->exists());
    }

    public function test_approving_an_already_handled_candidate_shows_an_error_instead_of_a_second_order(): void
    {
        $candidate = $this->pendingCandidate();
        $this->actingAs($this->makeUser('admin'));

        $this->post(route('preventive-candidates.approve', $candidate));
        $this->post(route('preventive-candidates.approve', $candidate))->assertSessionHas('error');

        $this->assertSame(1, MaintenanceOrder::where('source', MaintenanceOrder::SOURCE_AI)->count());
    }

    public function test_manager_can_trigger_a_scan_from_the_page(): void
    {
        $this->addHistory($this->makeDevice(), 'ononnn');
        $this->actingAs($this->makeUser('admin'));

        $this->post(route('preventive-candidates.scan'))
            ->assertRedirect(route('preventive-candidates.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, PreventiveCandidate::count());
    }

    public function test_admin_can_update_settings(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->put(route('ai-settings.update'), [
            'risk_threshold' => 0.75,
            'min_completed_orders' => 5,
            'recent_window' => 8,
            'dedup_window_days' => 21,
            'require_approval' => 0,
        ])->assertRedirect(route('ai-settings.edit'));

        $this->assertEqualsWithDelta(0.75, AiSetting::get(AiSetting::RISK_THRESHOLD), 0.0001);
        $this->assertSame(5, AiSetting::get(AiSetting::MIN_COMPLETED_ORDERS));
        $this->assertSame(8, AiSetting::get(AiSetting::RECENT_WINDOW));
        $this->assertSame(21, AiSetting::get(AiSetting::DEDUP_WINDOW_DAYS));
        $this->assertFalse(AiSetting::get(AiSetting::REQUIRE_APPROVAL));
        $this->assertTrue(AuditLog::where('action', 'ai_settings_updated')->exists());
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->from(route('ai-settings.edit'))
            ->put(route('ai-settings.update'), [
                'risk_threshold' => 5,
                'min_completed_orders' => 0,
                'recent_window' => 8,
                'dedup_window_days' => 21,
                'require_approval' => 1,
            ])
            ->assertSessionHasErrors(['risk_threshold', 'min_completed_orders']);

        $this->assertEqualsWithDelta(0.5, AiSetting::get(AiSetting::RISK_THRESHOLD), 0.0001);
    }

    public function test_settings_page_shows_current_values(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->get(route('ai-settings.edit'))->assertOk()->assertSee('風險分數門檻');
    }
}
