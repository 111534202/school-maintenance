<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Services\PartStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 零件履歷：使用紀錄、採購紀錄、報廢紀錄沒有表格檢視（固定清單）；最下面「匯出 Excel／PDF」先預覽，
 * 可以勾選要匯出的區塊（基本資料、使用紀錄、採購紀錄、報廢紀錄）與欄位。
 * 零件庫存列表的低庫存提示只顯示筆數，不列出零件（按按鈕查看）。
 */
class PartHistoryExportTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makePart(): Part
    {
        $part = Part::create(['name' => 'HDMI 線', 'specification' => '2公尺', 'unit' => '條', 'current_stock' => 0, 'safety_stock' => 3]);
        app(PartStockService::class)->receive($part, ['purchase_date' => '2026-10-01', 'vendor_name' => '五金行', 'quantity' => 15, 'unit_price' => 10, 'note' => null]);

        return $part->fresh();
    }

    public function test_history_page_offers_an_export_preview_and_a_scrap_section(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $this->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('匯出零件履歷')
            ->assertSeeInOrder(['基本資料', '採購紀錄', '使用紀錄', '報廢紀錄'])   // 報廢紀錄接在最後
            ->assertSee('name="cols[purchases][]"', false)
            ->assertSee('五金行');
    }

    public function test_history_export_uses_the_selected_sections_and_columns(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $this->get(route('parts.history.export', [$part, 'excel', 'sections' => ['basic', 'purchases'], 'cols' => ['basic' => [0, 5], 'purchases' => [0, 1, 3]]]))
            ->assertOk()->assertDownload();
        $this->get(route('parts.history.export', [$part, 'pdf', 'sections' => ['purchases'], 'cols' => ['purchases' => [0, 1]]]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get(route('parts.history.export', [$part, 'excel', 'sections' => ['purchases'], 'cols' => []]))->assertSessionHasErrors('cols');
        $this->get(route('parts.history.export', [$part, 'excel']))->assertSessionHasErrors('sections');
    }

}
