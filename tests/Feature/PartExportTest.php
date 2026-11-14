<?php

namespace Tests\Feature;

use App\Exports\PartsExport;
use App\Models\Part;
use App\Services\PartStockService;
use App\Support\PartExportColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 零件庫存列表：跟空間申請一樣的一大張卡片清單（沒有「表格檢視」切換），每頁 15 筆；最下面「匯出 Excel／PDF」先預覽，
 * 勾選要匯出的欄位與零件，匯出的內容跟零件履歷「基本資料」一樣。
 */
class PartExportTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makePart(string $name, int $stock, int $price): Part
    {
        $part = Part::create(['name' => $name, 'specification' => '2公尺', 'brand' => '牌子', 'model' => 'M1', 'unit' => '條', 'current_stock' => 0, 'safety_stock' => 3]);
        app(PartStockService::class)->receive($part, ['purchase_date' => '2026-10-01', 'vendor_name' => '廠商', 'quantity' => $stock, 'unit_price' => $price, 'note' => null]);

        return $part->fresh();
    }

    public function test_list_has_no_table_toggle_and_offers_an_export_preview(): void
    {
        $this->loginAsAnyUser();
        $hdmi = $this->makePart('HDMI 線', 9, 10);
        $this->makePart('網路線', 2, 30);

        $this->get(route('parts.index'))
            ->assertOk()
            ->assertSee('tb-table-wrap', false)
            ->assertSee('顯示第 1 到 2 筆，共 2 筆')
            ->assertSee('匯出 Excel／PDF')
            ->assertSee('匯出零件清單')
            ->assertSeeInOrder(['零件編號', '名稱', '規格', '品牌/型號', '平均單價', '目前庫存', '安全庫存'])
            ->assertSee('name="ids[]" value="' . $hdmi->id . '"', false)
            ->assertSee('NT$ 10')
            ->assertSee('9 條');
    }

    public function test_export_preview_follows_the_current_search(): void
    {
        $this->loginAsAnyUser();
        $this->makePart('HDMI 線', 9, 10);
        $cable = $this->makePart('網路線', 2, 30);

        $this->get(route('parts.index', ['keyword' => '網路']))
            ->assertSee('name="ids[]" value="' . $cable->id . '"', false)
            ->assertSee('目前搜尋「網路」');
    }

    public function test_excel_contains_only_the_checked_parts_and_columns(): void
    {
        $this->loginAsAnyUser();
        $hdmi = $this->makePart('HDMI 線', 9, 10);
        $this->makePart('網路線', 2, 30);

        $this->get(route('parts.export', ['excel', 'ids' => [$hdmi->id], 'columns' => ['part_code', 'average_price', 'current_stock']]))
            ->assertOk()
            ->assertDownload();

        $export = new PartsExport(Part::withStockValue()->whereKey($hdmi->id)->get(), PartExportColumns::pick(['current_stock', 'part_code', 'average_price']));
        $this->assertSame(['零件編號', '平均單價', '目前庫存'], $export->headings());   // 依固定順序
        $this->assertSame([[$hdmi->part_code, 'NT$ 10', '9 條']], $export->array());
    }

    public function test_pdf_export_downloads_and_requires_a_selection(): void
    {
        $this->loginAsAnyUser();
        $hdmi = $this->makePart('HDMI 線', 9, 10);

        $this->get(route('parts.export', ['pdf', 'ids' => [$hdmi->id], 'columns' => ['part_code', 'name']]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('parts.export', ['excel', 'columns' => ['name']]))->assertSessionHasErrors('ids');
        $this->get(route('parts.export', ['excel', 'ids' => [$hdmi->id]]))->assertSessionHasErrors('columns');
    }

    public function test_list_shows_fifteen_parts_per_page_with_chinese_pagination(): void
    {
        $this->loginAsAnyUser();
        foreach (range(1, 16) as $i) {
            Part::create(['name' => '零件' . $i, 'unit' => '個', 'current_stock' => 5, 'safety_stock' => 1]);
        }

        $this->get(route('parts.index'))
            ->assertSee('顯示第')->assertSee('筆，共')->assertDontSee('Showing')
            ->assertSee('零件15 的零件資料')->assertDontSee('零件16 的零件資料');   // 第 16 筆在第 2 頁（匯出預覽仍列出全部）
        $this->get(route('parts.index', ['page' => 2]))->assertSee('零件16 的零件資料');
    }

    public function test_clicking_a_part_opens_a_summary_popup_with_a_link_to_the_history(): void
    {
        $this->loginAsAnyUser();
        $hdmi = $this->makePart('HDMI 線', 2, 10);   // 安全庫存 3 → 低庫存

        $this->get(route('parts.summary', ['part' => $hdmi, 'modal' => 1]))
            ->assertOk()
            ->assertSeeInOrder([$hdmi->part_code, 'HDMI 線', '品牌/型號', '平均單價', '目前庫存', '安全庫存', '庫存狀態', '⚠ 低庫存', '查看履歷'])   // 只顯示基本資料（左右兩欄清單）＋查看履歷
            ->assertSee('NT$ 10')
            ->assertSee('href="' . route('parts.show', $hdmi) . '"', false)
            ->assertDontSee('<h1', false)
            ->assertDontSee('在零件庫存列表的對話框裡打開');
    }
}
