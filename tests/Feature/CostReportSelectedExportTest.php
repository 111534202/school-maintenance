<?php

namespace Tests\Feature;

use App\Exports\SectionsExport;
use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\RepairCostAnalysisService;
use App\Support\CostReportSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 成本分析報表：篩選直接顯示在頁面上；備品使用彙總、材料費明細、報廢明細沒有表格檢視；
 * 最下面「匯出 Excel／PDF」先預覽，可以勾選要匯出的區塊與欄位。
 */
class CostReportSelectedExportTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makeUsage(User $reporter, string $partName, int $quantity, ?int $unitPrice): void
    {
        $repairRequest = RepairRequest::factory()->create(['requester_id' => $reporter->id, 'assigned_to' => null]);
        $repairLog = RepairLog::factory()->create(['repair_request_id' => $repairRequest->id, 'created_at' => '2026-10-10 09:00:00']);
        $part = Part::create(['name' => $partName, 'unit' => '條', 'current_stock' => 10, 'safety_stock' => 1]);
        RepairPartUsage::create(['repair_log_id' => $repairLog->id, 'part_id' => $part->id, 'quantity' => $quantity, 'unit_price' => $unitPrice]);
    }

    public function test_page_offers_an_export_preview(): void
    {
        $user = $this->makeUserWithPermissions(['maintenance.view']);
        $this->makeUsage($user, 'HDMI 線', 12, 10);

        $this->actingAs($user)->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('匯出成本分析報表')
            ->assertSeeInOrder(['成本總覽', '備品使用彙總', '材料費明細', '報廢明細'])
            ->assertSee('name="cols[details][]"', false);
    }

    public function test_selected_sections_and_columns_are_the_only_ones_exported(): void
    {
        $user = $this->makeUserWithPermissions(['maintenance.view']);
        $this->makeUsage($user, 'HDMI 線', 12, 10);
        $this->makeUsage($user, '網路線', 2, null);

        $sections = CostReportSections::build(app(RepairCostAnalysisService::class)->generate($user, []));
        $picked = CostReportSections::pick($sections, ['parts', 'scraps'], ['parts' => [2, 0], 'scraps' => []]);

        // 報廢明細沒有勾任何欄位 → 不匯出；欄位依原本順序。
        $this->assertSame(['parts'], array_keys($picked));
        $this->assertSame(['備品名稱', '已知材料費小計'], $picked['parts']['headings']);
        $this->assertSame([1], $picked['parts']['numeric']);

        $sheets = (new SectionsExport($picked))->sheets();
        $this->assertCount(1, $sheets);
        $this->assertSame('備品使用彙總', $sheets[0]->title());
        $this->assertContains(['HDMI 線', 120.0], $sheets[0]->array());

        // 材料費明細的案件狀態換成中文。
        $statusIndex = array_search('案件狀態', $sections['details']['headings'], true);
        $this->assertNotContains($sections['details']['rows'][0][$statusIndex], ['pending', 'completed', 'assigned', 'in_progress', 'pending_review']);
    }

    public function test_export_routes_use_the_selection_and_require_at_least_one_column(): void
    {
        $user = $this->makeUserWithPermissions(['maintenance.view']);
        $this->makeUsage($user, 'HDMI 線', 12, 10);
        $this->actingAs($user);

        $this->get(route('repair-cost-analysis.export.excel', ['sections' => ['parts'], 'cols' => ['parts' => [0, 1]]]))
            ->assertOk()->assertDownload();
        $this->get(route('repair-cost-analysis.export.pdf', ['sections' => ['summary', 'details'], 'cols' => ['summary' => [0, 1], 'details' => [0, 7, 8]]]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get(route('repair-cost-analysis.export.excel', ['sections' => ['parts'], 'cols' => []]))->assertSessionHasErrors('cols');
        $this->get(route('repair-cost-analysis.export.excel', ['sections' => ['nope']]))->assertSessionHasErrors('sections.0');

        // 沒有從預覽視窗送出（沒有 sections）時，維持原本的完整匯出。
        $this->get(route('repair-cost-analysis.export.excel'))->assertOk()->assertDownload();
    }
}
