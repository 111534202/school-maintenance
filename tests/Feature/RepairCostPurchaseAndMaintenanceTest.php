<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Services\RepairCostAnalysisService;
use App\Support\CostReportSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 成本分析報表：進貨成本（買進零件花的錢）與保養用料（併入材料費）。
 */
class RepairCostPurchaseAndMaintenanceTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function makePart(string $name = '測試零件'): Part
    {
        return Part::create(['name' => $name, 'unit' => '個', 'current_stock' => 0, 'safety_stock' => 1]);
    }

    private function buy(Part $part, string $date, int $qty, int $price, ?string $vendor = '甲廠商', ?string $note = null): void
    {
        $part->purchases()->create([
            'purchase_date' => $date, 'vendor_name' => $vendor, 'quantity' => $qty,
            'unit_price' => $price, 'remaining_quantity' => $qty, 'note' => $note,
        ]);
    }

    private function maintenanceUsage(Part $part, string $executedAt, int $qty, ?int $price): MaintenanceResult
    {
        $order = MaintenanceOrder::factory()->create();   // 沒指定設備：畫面顯示「未關聯設備」
        $result = MaintenanceResult::factory()->create(['maintenance_order_id' => $order->id, 'executed_at' => $executedAt]);
        $result->partUsages()->create(['part_id' => $part->id, 'quantity' => $qty, 'unit_price' => $price]);

        return $result;
    }

    public function test_purchase_cost_sums_batches_in_range_including_opening_stock(): void
    {
        $user = $this->makeUserWithPermissions(['parts.manage']);
        $part = $this->makePart();
        $this->buy($part, '2026-11-10', 10, 50);                                    // 500，期間內
        $this->buy($part, '2026-11-12', 2, 100);                                    // 200，期間內
        $this->buy($part, '2026-11-11', 90, 155, null, '期初庫存');                  // 13,950，期初庫存也算入庫（報廢常從它扣），另外標示
        $this->buy($part, '2026-10-01', 5, 1000);                                   // 期間外

        $data = app(RepairCostAnalysisService::class)->generate($user, ['start_date' => '2026-11-01', 'end_date' => '2026-11-30']);

        $this->assertSame(14650, $data['purchaseTotal']);
        $this->assertSame(13950, $data['purchaseOpeningTotal']);
        $this->assertSame(3, $data['purchaseCount']);

        $this->actingAs($user)->get(route('repair-cost-analysis.index', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30']))
            ->assertOk()
            ->assertSee('進貨成本')
            ->assertSee('進貨金額 NT$ 14,650')
            ->assertSee('其中期初庫存 NT$ 13,950')
            ->assertSee('甲廠商');
    }

    public function test_maintenance_usage_is_added_to_material_cost_and_part_summary(): void
    {
        $user = $this->makeUserWithPermissions(['parts.manage']);
        $part = $this->makePart('濾網');
        $this->maintenanceUsage($part, '2026-11-10 09:00:00', 3, 40);   // 120
        $this->maintenanceUsage($part, '2026-11-11 09:00:00', 1, null); // 缺單價

        // 同一個零件也有維修用料：彙總要加在一起。
        $request = RepairRequest::factory()->create(['requester_id' => $user->id, 'assigned_to' => null]);
        $log = RepairLog::factory()->create(['repair_request_id' => $request->id, 'created_at' => '2026-11-12 09:00:00']);
        RepairPartUsage::create(['repair_log_id' => $log->id, 'part_id' => $part->id, 'quantity' => 2, 'unit_price' => 40]);   // 80

        $data = app(RepairCostAnalysisService::class)->generate($user, []);

        $this->assertSame(200, (int) $data['knownSubtotal']);
        $this->assertSame(80, (int) $data['repairSubtotal']);
        $this->assertSame(120, (int) $data['maintenanceSubtotal']);
        $this->assertSame(1, $data['missingPriceCount']);
        $this->assertCount(1, $data['partUsageSummary']);
        $row = $data['partUsageSummary']->first();
        $this->assertSame([6, 200, 1], [$row->total_quantity, $row->known_subtotal, $row->missing_price_count]);

        $this->actingAs($user)->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('保養用料明細')
            ->assertSee('未關聯設備')
            ->assertSee('MO-', false)
            ->assertSee('維修用料 NT$ 80＋保養用料 NT$ 120');
    }

    public function test_maintenance_usage_respects_date_range_and_is_hidden_when_a_repair_status_is_chosen(): void
    {
        $user = $this->makeUserWithPermissions(['parts.manage']);
        $part = $this->makePart();
        $this->maintenanceUsage($part, '2026-11-10 09:00:00', 1, 100);
        $this->maintenanceUsage($part, '2026-09-10 09:00:00', 1, 999);

        $service = app(RepairCostAnalysisService::class);
        $this->assertSame(100, (int) $service->generate($user, ['start_date' => '2026-11-01'])['maintenanceSubtotal']);
        $this->assertSame(1099, (int) $service->generate($user, [])['maintenanceSubtotal']);
        // 案件狀態是報修單的條件：選了就只看維修用料。
        $this->assertSame(0, (int) $service->generate($user, ['status' => 'completed'])['maintenanceSubtotal']);
    }

    public function test_period_filter_offers_all_month_and_week_and_hides_the_date_pickers(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));   // 週三
        $user = $this->makeUserWithPermissions(['parts.manage']);
        $part = $this->makePart();
        $this->buy($part, '2026-11-16', 1, 100);   // 當周（11/16 週一 ~ 11/22 週日）
        $this->buy($part, '2026-11-03', 1, 200);   // 當月、非當周
        $this->buy($part, '2026-10-20', 1, 400);   // 上個月

        $total = fn (array $query) => $this->actingAs($user)->get(route('repair-cost-analysis.index', $query))->assertOk()->viewData('purchaseTotal');

        $this->assertSame(700, $total([]));
        $this->assertSame(300, $total(['period' => 'month']));
        $this->assertSame(100, $total(['period' => 'week']));

        $this->actingAs($user)->get(route('repair-cost-analysis.index', ['period' => 'week']))
            ->assertSee('name="period"', false)
            ->assertSee('當月')
            ->assertSee('當周')
            ->assertDontSee('name="start_date"', false)
            ->assertDontSee('name="end_date"', false);

        $this->get(route('repair-cost-analysis.index', ['period' => 'year']))->assertSessionHasErrors('period');
    }

    public function test_exports_include_purchase_and_maintenance_sections(): void
    {
        $user = $this->makeUserWithPermissions(['parts.manage']);
        $part = $this->makePart('投影機燈泡');
        $this->buy($part, '2026-11-10', 4, 250);
        $this->maintenanceUsage($part, '2026-11-10 09:00:00', 2, 250);

        $sections = CostReportSections::build(app(RepairCostAnalysisService::class)->generate($user, []));

        $this->assertSame(['summary', 'parts', 'details', 'maintenance', 'scraps', 'purchases'], array_keys($sections));
        $this->assertSame(500.0, $sections['maintenance']['rows'][0][7]);
        $this->assertSame('投影機燈泡', $sections['purchases']['rows'][0][2]);
        $this->assertSame(1000.0, $sections['purchases']['rows'][0][7]);

        $this->actingAs($user);
        $this->get(route('repair-cost-analysis.export.excel'))->assertOk();
        $this->get(route('repair-cost-analysis.export.excel', ['sections' => ['purchases', 'maintenance'], 'cols' => ['purchases' => [0, 7], 'maintenance' => [0, 7]]]))->assertOk();
        $this->get(route('repair-cost-analysis.export.pdf'))->assertOk();
        $this->get(route('repair-cost-analysis.export.pdf', ['sections' => ['purchases'], 'cols' => ['purchases' => [0, 2, 7]]]))->assertOk();
    }
}
