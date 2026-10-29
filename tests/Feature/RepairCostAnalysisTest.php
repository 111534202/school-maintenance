<?php

namespace Tests\Feature;

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
            'reporter_id' => $reporter->id,
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
}