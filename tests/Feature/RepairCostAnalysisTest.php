<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class RepairCostAnalysisTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    /**
     * 建立具有成本分析檢視權限的測試使用者。
     */
    private function makeCostViewer(): User
    {
        return $this->makeUserWithPermissions(['maintenance.view']);
    }

    /**
     * 建立一筆指定維修日期、數量及歷史單價的用料明細。
     */
    private function makeUsage(
        User $reporter,
        string $createdAt,
        string $partName,
        int $quantity,
        ?int $unitPrice
    ): RepairPartUsage {
        $repairRequest = RepairRequest::factory()->create([
            'requester_id' => $reporter->id,
            'assigned_to' => null,
        ]);

        $repairLog = RepairLog::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $part = Part::create([
            'name' => $partName,
            'specification' => '成本分析測試規格',
            'unit_price' => 999,
            'current_stock' => 10,
            'safety_stock' => 1,
        ]);

        return RepairPartUsage::create([
            'repair_log_id' => $repairLog->id,
            'part_id' => $part->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);
    }

    /**
     * 使用歷史單價計算材料費，而不是備品目前的單價。
     */
    public function test_cost_uses_historical_unit_price_snapshot(): void
    {
        $user = $this->makeCostViewer();
        $this->actingAs($user);

        $usage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '歷史單價測試備品',
            2,
            150
        );

        // 模擬備品目前單價已變更，但歷史用料明細不得跟著改變。
        $usage->part->update(['unit_price' => 200]);

        $response = $this->get(route('repair-cost-analysis.index'));

        $response->assertOk()
            ->assertViewHas('knownSubtotal', 300)
            ->assertViewHas('missingPriceCount', 0)
            ->assertSee('歷史單價測試備品')
            ->assertSee('NT$ 150')
            ->assertSee('NT$ 300');
    }

    /**
     * 缺少歷史單價時，不得將該筆成本當成零元或計入已知小計。
     */
    public function test_missing_unit_price_is_reported_without_inflating_known_subtotal(): void
    {
        $user = $this->makeCostViewer();
        $this->actingAs($user);

        $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '有單價測試備品',
            2,
            150
        );

        $this->makeUsage(
            $user,
            '2026-10-10 10:00:00',
            '缺少單價測試備品',
            3,
            null
        );

        $response = $this->get(route('repair-cost-analysis.index'));

        $response->assertOk()
            ->assertViewHas('knownSubtotal', 300)
            ->assertViewHas('missingPriceCount', 1)
            ->assertViewHas('hasIncompleteCost', true)
            ->assertSee('有單價測試備品')
            ->assertSee('缺少單價測試備品')
            ->assertSee('缺少單價')
            ->assertSee('材料費總額尚不完整');
    }

    /**
     * 開始與結束日期都應包含當天，範圍以維修紀錄建立時間為準。
     */
    public function test_date_filter_includes_both_boundary_dates(): void
    {
        $user = $this->makeCostViewer();
        $this->actingAs($user);

        $this->makeUsage(
            $user,
            '2026-10-09 23:59:59',
            '開始日期之前的備品',
            1,
            100
        );

        $this->makeUsage(
            $user,
            '2026-10-10 00:00:00',
            '開始日期當天的備品',
            1,
            100
        );

        $this->makeUsage(
            $user,
            '2026-10-12 23:59:59',
            '結束日期當天的備品',
            1,
            200
        );

        $this->makeUsage(
            $user,
            '2026-10-13 00:00:00',
            '結束日期之後的備品',
            1,
            300
        );

        $response = $this->get(route('repair-cost-analysis.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
        ]));

        $response->assertOk()
            ->assertSee('開始日期當天的備品')
            ->assertSee('結束日期當天的備品')
            ->assertDontSee('開始日期之前的備品')
            ->assertDontSee('結束日期之後的備品')
            ->assertViewHas('knownSubtotal', 300);
    }

    /**
     * 未填日期時，應顯示所有日期中使用者有權檢視的紀錄。
     */
    public function test_omitting_dates_shows_all_visible_dates(): void
    {
        $user = $this->makeCostViewer();
        $this->actingAs($user);

        $this->makeUsage(
            $user,
            '2025-01-15 10:00:00',
            '較早日期的備品',
            1,
            100
        );

        $this->makeUsage(
            $user,
            '2026-10-15 10:00:00',
            '較新日期的備品',
            2,
            200
        );

        $response = $this->get(route('repair-cost-analysis.index'));

        $response->assertOk()
            ->assertSee('較早日期的備品')
            ->assertSee('較新日期的備品')
            ->assertViewHas('knownSubtotal', 500);
    }

    /**
     * 一般檢視者不得看到自己未報修、也未被指派的其他案件。
     */
    public function test_cost_analysis_only_shows_repair_requests_visible_to_user(): void
    {
        $user = $this->makeCostViewer();
        $otherUser = $this->makeCostViewer();

        $this->actingAs($user);

        $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '本人案件的備品',
            1,
            100
        );

        $this->makeUsage(
            $otherUser,
            '2026-10-10 10:00:00',
            '其他案件的備品',
            1,
            900
        );

        $response = $this->get(route('repair-cost-analysis.index'));

        $response->assertOk()
            ->assertSee('本人案件的備品')
            ->assertDontSee('其他案件的備品')
            ->assertViewHas('knownSubtotal', 100);
    }

    /**
     * 沒有 maintenance.view 權限的使用者不得進入成本分析頁面。
     */
    public function test_user_without_maintenance_view_permission_is_forbidden(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index'))
            ->assertForbidden();
    }

    /**
     * 結束日期早於開始日期時，應回傳日期驗證錯誤。
     */
    public function test_end_date_cannot_be_earlier_than_start_date(): void
    {
        $user = $this->makeCostViewer();

        $this->actingAs($user)
            ->from(route('repair-cost-analysis.index'))
            ->get(route('repair-cost-analysis.index', [
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-10',
            ]))
            ->assertRedirect(route('repair-cost-analysis.index'))
            ->assertSessionHasErrors('end_date');
    }

    /**
     * 未指定狀態時，成本分析應包含不同狀態案件的用料。
     */
    public function test_cost_analysis_includes_all_repair_statuses_by_default(): void
    {
        $user = $this->makeCostViewer();

        $pendingUsage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '待處理案件備品',
            2,
            100
        );

        $completedUsage = $this->makeUsage(
            $user,
            '2026-10-10 10:00:00',
            '已完成案件備品',
            1,
            300
        );

        $pendingUsage->repairLog->repairRequest->update([
            'status' => 'pending',
        ]);

        $completedUsage->repairLog->repairRequest->update([
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('待處理案件備品')
            ->assertSee('已完成案件備品')
            ->assertViewHas('knownSubtotal', 500);
    }

    /**
     * 指定案件狀態時，只顯示該狀態的用料。
     */
    public function test_cost_analysis_can_filter_by_repair_status(): void
    {
        $user = $this->makeCostViewer();

        $pendingUsage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '待處理案件備品',
            2,
            100
        );

        $completedUsage = $this->makeUsage(
            $user,
            '2026-10-10 10:00:00',
            '已完成案件備品',
            1,
            300
        );

        $pendingUsage->repairLog->repairRequest->update([
            'status' => 'pending',
        ]);

        $completedUsage->repairLog->repairRequest->update([
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index', [
                'status' => 'completed',
            ]))
            ->assertOk()
            ->assertSee('已完成案件備品')
            ->assertDontSee('待處理案件備品')
            ->assertViewHas('knownSubtotal', 300);
    }

    /**
     * 同一案件有多筆用料時，案件數應去重，備品使用量應正確彙總。
     */
    public function test_cost_analysis_summarizes_unique_requests_and_part_usage(): void
    {
        $user = $this->makeCostViewer();

        $firstUsage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '彙總測試備品',
            2,
            100
        );

        $secondUsage = $this->makeUsage(
            $user,
            '2026-10-10 10:00:00',
            '另一筆測試用料',
            3,
            100
        );

        // 讓兩筆用料共用同一個維修案件與備品。
        $secondUsage->repairLog->update([
            'repair_request_id' => $firstUsage->repairLog->repair_request_id,
        ]);

        $secondUsage->update([
            'part_id' => $firstUsage->part_id,
        ]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertViewHas('repairRequestCount', 1)
            ->assertViewHas('partUsageSummary', function ($summary) {
                $item = $summary->first();

                return $summary->count() === 1
                    && $item->part_id !== null
                    && (int) $item->total_quantity === 5
                    && (float) $item->known_subtotal === 500.0
                    && (int) $item->missing_price_count === 0;
            });
    }
    /**
     * 成本分析明細應顯示案件狀態、設備編號與教室資訊。
     */
    public function test_cost_analysis_detail_shows_repair_status_device_and_classroom(): void
    {
        $user = $this->makeCostViewer();

        $usage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '明細顯示測試備品',
            2,
            150
        );

        $classroom = Classroom::create([
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '1F',
            'classroom_code' => 'COST-A101',
            'name' => '成本測試教室',
        ]);

        $category = DeviceCategory::create([
            'name' => '成本測試投影機',
        ]);

        $device = Device::create([
            'device_code' => 'COST-DEV-001',
            'device_category_id' => $category->id,
            'classroom_id' => $classroom->id,
            'status' => 'normal',
        ]);

        $usage->repairLog->repairRequest->update([
            'title' => '成本明細設備案件',
            'status' => 'completed',
            'device_id' => $device->id,
        ]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('成本明細設備案件')
            ->assertSee('已結案')
            ->assertSee('COST-DEV-001')
            ->assertSee('成本測試教室')
            ->assertSee('COST-A101')
            ->assertSee('明細顯示測試備品');
    }

    /**
     * 沒有關聯設備時，明細應使用案件的設備描述與地點。
     */
    public function test_cost_analysis_detail_shows_fallback_for_request_without_device(): void
    {
        $user = $this->makeCostViewer();

        $usage = $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '無設備案件測試備品',
            1,
            200
        );

        $usage->repairLog->repairRequest->update([
            'title' => '無設備成本案件',
            'status' => 'pending',
            'device_id' => null,
            'device_note' => '手動填寫的老舊冷氣',
            'location' => 'B棟二樓走廊',
        ]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('無設備成本案件')
            ->assertSee('新報修')
            ->assertSee('手動填寫的老舊冷氣')
            ->assertSee('B棟二樓走廊')
            ->assertSee('無設備案件測試備品');
    }
}