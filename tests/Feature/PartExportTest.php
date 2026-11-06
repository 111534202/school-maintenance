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
 * 零件庫存列表：固定清單檢視（沒有「表格檢視」切換）；最下面「匯出 Excel／PDF」先預覽，
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
            ->assertSee('data-onion-list-only', false)
            ->assertSee('匯出 Excel／PDF')
            ->assertSee('匯出零件清單')
            ->assertSeeInOrder(['零件編號', '名稱', '規格', '品牌／型號', '平均單價', '目前庫存', '安全庫存'])
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
}
