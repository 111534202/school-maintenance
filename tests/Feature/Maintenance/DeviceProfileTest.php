<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Services\AI\PreventiveCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 5、6：設備履歷頁（保養紀錄來源標籤、AI 風險評估）。
 */
class DeviceProfileTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->actingAs($this->makeUser('technician'));
    }

    public function test_profile_shows_periodic_and_ai_source_labels(): void
    {
        $device = $this->makeDevice('T-PROF');
        $this->addHistory($device, 'ononnn');
        $this->makeOpenOrder($device, dueInDays: 1, source: MaintenanceOrder::SOURCE_AI);

        $this->get(route('device-profile.show', $device))
            ->assertOk()
            ->assertSee('T-PROF')
            ->assertSee('定期')
            ->assertSee('AI 辨識');
    }

    public function test_profile_shows_the_ai_risk_breakdown(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');

        $this->get(route('device-profile.show', $device))
            ->assertOk()
            ->assertSee('AI 風險評估')
            ->assertSee('0.633')
            ->assertSee('結尾連續 NG 次數');
    }

    public function test_profile_explains_when_history_is_insufficient(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'nn');

        $this->get(route('device-profile.show', $device))
            ->assertOk()
            ->assertSee('保養歷史不足');
    }

    public function test_profile_lists_candidates_for_the_device(): void
    {
        $device = $this->makeDevice();
        $this->addHistory($device, 'ononnn');
        app(PreventiveCandidateService::class)->scan();

        $this->get(route('device-profile.show', $device))
            ->assertOk()
            ->assertSee('待審核');
    }
}
