<?php

namespace Tests\Feature\Maintenance;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\RepairLog;
use App\Models\RepairRequest;
use App\Services\AI\PreventiveCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 第 4 週任務 5、6：設備履歷頁（保養紀錄來源標籤、AI 風險評估）。
 * 第 5 週任務 3：設備履歷回歸（報修／維修紀錄／附件完整、不重複不遺漏、遵守報修單檢視權限）。
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

    private function repairWithLog(Device $device, string $title, string $status, array $logs = [], array $attrs = []): RepairRequest
    {
        $repair = RepairRequest::factory()->create(array_merge([
            'title' => $title,
            'status' => $status,
            'device_id' => $device->id,
        ], $attrs));

        foreach ($logs as $log) {
            RepairLog::factory()->create(array_merge(['repair_request_id' => $repair->id], $log));
        }

        return $repair;
    }

    private function attach(RepairRequest|RepairLog $owner, string $name): void
    {
        $owner->attachments()->create([
            'disk_path' => 'attachments/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1234,
        ]);
    }

    public function test_profile_lists_every_repair_log_and_attachment_for_two_devices_without_duplicates(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $deviceA = $this->makeDevice('T-REP-A');
        $deviceB = $this->makeDevice('T-REP-B');

        $repairA = $this->repairWithLog($deviceA, '風扇異音報修', 'completed', [
            ['cause' => '風扇軸承磨損', 'resolution' => '更換風扇', 'parts_used_note' => '散熱風扇 x1', 'total_hours' => 2.00],
            ['cause' => '複查仍有雜音', 'resolution' => '重新鎖固', 'parts_used_note' => null, 'total_hours' => 1.50],
        ]);
        $this->attach($repairA, 'req-photo.jpg');
        $this->attach($repairA->repairLogs()->orderBy('id')->first(), 'log-photo.jpg');

        $repairB = $this->repairWithLog($deviceB, '電源無法開機報修', 'in_progress', [
            ['cause' => '電源供應器故障', 'resolution' => '更換電源', 'total_hours' => 3.00],
        ]);
        $this->attach($repairB, 'b-photo.jpg');

        $htmlA = $this->get(route('device-profile.show', $deviceA))->assertOk()->getContent();
        $this->assertSame(1, substr_count($htmlA, '風扇異音報修'));
        $this->assertStringContainsString('維修紀錄 1', $htmlA);
        $this->assertStringContainsString('維修紀錄 2', $htmlA);
        $this->assertStringNotContainsString('維修紀錄 3', $htmlA);
        $this->assertStringContainsString('散熱風扇 x1', $htmlA);
        $this->assertStringContainsString('維修工時合計 3.50 小時', $htmlA);
        $this->assertSame(1, substr_count($htmlA, 'req-photo.jpg'));
        $this->assertSame(1, substr_count($htmlA, 'log-photo.jpg'));
        $this->assertStringContainsString('附件（2）', $htmlA);
        $this->assertStringNotContainsString('電源無法開機報修', $htmlA);
        $this->assertStringNotContainsString('b-photo.jpg', $htmlA);

        $htmlB = $this->get(route('device-profile.show', $deviceB))->assertOk()->getContent();
        $this->assertSame(1, substr_count($htmlB, '電源無法開機報修'));
        $this->assertStringContainsString('維修工時合計 3.00 小時', $htmlB);
        $this->assertStringContainsString('附件（1）', $htmlB);
        $this->assertStringNotContainsString('風扇異音報修', $htmlB);
    }

    public function test_profile_only_shows_repair_requests_the_viewer_may_see(): void
    {
        $technician = $this->makeUser('technician');
        $other = $this->makeUser('technician');
        $this->actingAs($technician);
        $device = $this->makeDevice('T-REP-PRIV');

        $this->repairWithLog($device, '指派給我的案件', 'assigned', [], ['assigned_to' => $technician->id]);
        $this->repairWithLog($device, '別人的機密案件', 'assigned', [], ['assigned_to' => $other->id]);

        $this->get(route('device-profile.show', $device))
            ->assertOk()
            ->assertSee('指派給我的案件')
            ->assertDontSee('別人的機密案件')
            ->assertSee('另有 1 張報修單因權限限制無法在此顯示');
    }

    public function test_ng_result_creates_a_repair_that_is_traceable_from_the_device_profile(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $device = $this->makeDevice('T-REP-NG');
        $order = MaintenanceOrder::create([
            'maintenance_plan_id' => $this->makePlan($device)->id,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ng',
            'executed_by' => '王小明',
            'executed_at' => '2026-10-07 10:00:00',
            'notes' => '風扇異音',
        ])->assertRedirect();

        $repair = RepairRequest::where('device_id', $device->id)->sole();
        $this->assertSame($repair->id, MaintenanceResult::firstOrFail()->repair_request_id);

        $html = $this->get(route('device-profile.show', $device))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '保養 NG 轉入'));
        $this->assertStringContainsString('保養工單 #'.$order->id.' 的 NG 結果', $html);
        $this->assertStringContainsString('保養檢查 NG：T-REP-NG', $html);
    }
}
