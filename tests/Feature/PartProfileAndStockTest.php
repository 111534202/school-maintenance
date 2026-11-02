<?php

namespace Tests\Feature;

use App\Models\MaintenancePartUsage;
use App\Models\Part;
use App\Models\PartPurchase;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 零件庫存：零件編號、零件履歷、採購紀錄（加庫存）、先進先出成本、保養用料。
 */
class PartProfileAndStockTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;
    use CreatesMaintenanceFixtures;

    private function makePart(array $attrs = []): Part
    {
        return Part::create($attrs + [
            'name' => 'HDMI 線',
            'specification' => '1公尺',
            'current_stock' => 0,
            'safety_stock' => 1,
        ]);
    }

    private function buy(Part $part, string $date, int $quantity, int $unitPrice): PartPurchase
    {
        return app(PartStockService::class)->receive($part, [
            'purchase_date' => $date, 'vendor_name' => '測試廠商', 'quantity' => $quantity, 'unit_price' => $unitPrice, 'note' => null,
        ]);
    }

    // ---------- 零件編號 ----------

    public function test_part_codes_follow_the_creation_order_and_each_specification_has_its_own_code(): void
    {
        $short = $this->makePart(['specification' => '1公尺']);
        $long = $this->makePart(['specification' => '5公尺']);

        $this->assertSame('PART-1', $short->part_code);
        $this->assertSame('PART-2', $long->part_code);
    }

    public function test_the_next_code_is_counted_numerically_not_alphabetically(): void
    {
        foreach (range(1, 10) as $i) {
            $part = $this->makePart(['name' => "零件 {$i}"]);
        }

        $this->assertSame('PART-10', $part->part_code);
        $this->assertSame('PART-11', Part::nextCode());
    }

    public function test_a_custom_code_can_be_given_when_creating_and_blank_falls_back_to_the_next_number(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('parts.store'), ['part_code' => 'HDMI-2M', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasNoErrors();
        $this->post(route('parts.store'), ['part_code' => '', 'name' => '網路線', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Part::where('part_code', 'HDMI-2M')->exists());
        $this->assertTrue(Part::where('part_code', 'PART-1')->exists(), '自己取的編號不影響自動編號的順序');
    }

    public function test_the_automatic_number_skips_a_code_someone_already_typed_by_hand(): void
    {
        $this->makePart(['part_code' => 'PART-1']);
        $this->makePart(['part_code' => 'part-2']);   // 大小寫不同也算同一個編號

        $this->assertSame('PART-3', Part::nextCode());
        $this->assertSame('PART-3', $this->makePart()->part_code);
    }

    public function test_a_duplicate_code_is_refused_when_creating_or_updating_ignoring_case(): void
    {
        $this->loginAsAnyUser();
        $first = $this->makePart(['part_code' => 'HDMI-2M']);
        $second = $this->makePart(['part_code' => 'NET-3M']);

        $this->post(route('parts.store'), ['part_code' => 'hdmi-2m', 'name' => '重複', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasErrors('part_code');
        $this->assertSame(2, Part::count());

        $this->put(route('parts.update', $second), ['part_code' => 'HDMI-2M', 'name' => '網路線', 'unit' => '條', 'safety_stock' => 1])
            ->assertSessionHasErrors('part_code');
        $this->assertSame('NET-3M', $second->fresh()->part_code);

        // 保留自己原本的編號不算重複。
        $this->put(route('parts.update', $first), ['part_code' => 'HDMI-2M', 'name' => 'HDMI 線（新名稱）', 'unit' => '條', 'safety_stock' => 1])
            ->assertSessionHasNoErrors();
    }

    public function test_the_code_can_be_changed_later_and_purchases_stay_attached(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);

        $this->put(route('parts.update', $part), ['part_code' => 'HDMI-1M', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1])
            ->assertRedirect(route('parts.show', $part));

        $this->assertSame('HDMI-1M', $part->fresh()->part_code);
        $this->assertSame(1, $part->fresh()->purchases()->count());
        $this->get(route('parts.show', $part))->assertSee('零件履歷：HDMI-1M HDMI 線（1公尺）');
    }

    public function test_the_code_must_use_allowed_characters_and_is_required_when_editing(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $this->post(route('parts.store'), ['part_code' => 'HDMI 2M', 'name' => '空白', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasErrors('part_code');
        $this->put(route('parts.update', $part), ['part_code' => '', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1])
            ->assertSessionHasErrors('part_code');
    }

    public function test_the_live_check_reports_whether_a_code_is_available(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart(['part_code' => 'HDMI-2M']);

        $this->getJson(route('parts.check-code', ['code' => 'hdmi-2m']))->assertJson(['available' => false]);
        $this->getJson(route('parts.check-code', ['code' => 'HDMI-2M', 'ignore' => $part->id]))->assertJson(['available' => true]);
        $this->getJson(route('parts.check-code', ['code' => 'NEW-1']))->assertJson(['available' => true]);
        $this->getJson(route('parts.check-code', ['code' => 'bad code']))->assertJson(['available' => false]);
        $this->getJson(route('parts.check-code', ['code' => '']))->assertJson(['available' => true]);
    }

    public function test_unit_is_required_and_shown_with_stock_quantities(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('parts.store'), ['name' => '無單位', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasErrors('unit');

        $this->post(route('parts.store'), ['name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 2, 'current_stock' => 3, 'unit_price' => 20])
            ->assertSessionHasNoErrors();
        $part = Part::where('name', 'HDMI 線')->firstOrFail();

        $this->get(route('parts.index'))->assertSee('3 條')->assertSee('2 條');
        $this->get(route('parts.show', $part))->assertSee('3 條');
    }

    // ---------- 列表與搜尋 ----------

    public function test_list_title_and_search_by_code_name_brand_or_model(): void
    {
        $this->loginAsAnyUser();
        // 庫存充足，避免出現在「低庫存品項」彈窗（彈窗不受搜尋條件影響，永遠列出全部低庫存零件）。
        $this->makePart(['name' => 'HDMI 線', 'brand' => 'Cable Master', 'model' => 'HD-1M', 'current_stock' => 5, 'unit_price' => 20]);
        $this->makePart(['name' => '網路線', 'brand' => 'D-Link', 'model' => 'CAT6', 'current_stock' => 5, 'unit_price' => 20]);

        $this->get(route('parts.index'))->assertOk()->assertSee('零件庫存列表')->assertSee('PART-1')->assertSee('PART-2');

        $this->get(route('parts.index', ['keyword' => 'PART-2']))->assertSee('網路線')->assertDontSee('HD-1M');
        $this->get(route('parts.index', ['keyword' => 'Cable']))->assertSee('HDMI 線')->assertDontSee('CAT6');
        $this->get(route('parts.index', ['keyword' => 'CAT6']))->assertSee('網路線')->assertDontSee('HD-1M');
    }

    public function test_list_shows_the_weighted_average_price_of_the_remaining_stock(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);
        $this->buy($part, '2026-10-12', 2, 30);

        $this->get(route('parts.index'))->assertSee('NT$ 25');
    }

    public function test_low_stock_window_lists_only_parts_below_their_safety_stock(): void
    {
        $this->loginAsAnyUser();
        $low = $this->makePart(['name' => '快用完的線', 'part_code' => 'LOW-1', 'safety_stock' => 5]);
        $this->buy($low, '2026-10-10', 2, 20);
        $enough = $this->makePart(['name' => '庫存充足的線', 'part_code' => 'OK-1', 'safety_stock' => 1]);
        $this->buy($enough, '2026-10-10', 9, 20);

        $html = $this->get(route('parts.index'))
            ->assertOk()
            ->assertSee('查看目前低庫存品項')
            ->assertSee('目前低庫存品項（1）')
            ->assertSee('data-href="' . route('parts.show', $low) . '"', false)
            ->getContent();

        // 低庫存視窗裡只有 LOW-1。
        $modal = substr($html, strpos($html, 'id="lowStockModal"'));
        $this->assertStringContainsString('LOW-1', $modal);
        $this->assertStringNotContainsString('OK-1', $modal);

        // 列表上「低庫存」標籤在數量前面。
        $this->assertMatchesRegularExpression('/低庫存<\/span>\s*2/u', $html);
    }

    public function test_low_stock_parts_can_be_exported_to_excel(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->loginAsAnyUser();
        $low = $this->makePart(['part_code' => 'LOW-1', 'unit' => '條', 'safety_stock' => 5]);
        $this->buy($low, '2026-10-10', 2, 20);
        $enough = $this->makePart(['part_code' => 'OK-1', 'safety_stock' => 1]);
        $this->buy($enough, '2026-10-10', 9, 20);

        $this->get(route('parts.index'))->assertSee(route('parts.low-stock.export'), false);
        $this->get(route('parts.low-stock.export'))->assertOk();

        \Maatwebsite\Excel\Facades\Excel::matchByRegex();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('/^low-stock-parts-.*\\.xlsx$/', function ($export): bool {
            $rows = $export->array();

            return $export instanceof \App\Exports\LowStockPartsExport
                && count($rows) === 1
                && $rows[0][0] === 'LOW-1'
                && $rows[0][4] === 2
                && $rows[0][6] === '條'
                && $rows[0][7] === 3;
        });
    }

    // ---------- 零件履歷 ----------

    public function test_profile_shows_title_basic_data_purchases_and_usages(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart(['brand' => 'Cable Master', 'model' => 'HD-1M', 'safety_stock' => 5]);
        $this->buy($part, '2026-10-10', 2, 20);

        $this->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('零件履歷：PART-1 HDMI 線（1公尺）')
            ->assertSee('Cable Master／HD-1M')
            ->assertSee('低庫存')
            ->assertSee('測試廠商')
            ->assertSee('NT$ 40')            // 總價＝2×20
            ->assertSee('修改基本資料')
            ->assertSee('新增採購');
    }

    public function test_part_pages_require_the_parts_permission(): void
    {
        $part = $this->makePart();
        $purchase = $this->buy($part, '2026-10-10', 1, 20);

        $this->actingAs($this->makeUserWithPermissions(['maintenance.view']));
        $this->get(route('parts.show', $part))->assertForbidden();
        $this->get(route('parts.purchases.create', $part))->assertForbidden();
        $this->delete(route('parts.purchases.destroy', [$part, $purchase]))->assertForbidden();

        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $this->get(route('parts.show', $part))->assertOk();
    }

    // ---------- 採購紀錄 ----------

    public function test_adding_a_purchase_increases_stock_and_keeps_the_invoice(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $this->post(route('parts.purchases.store', $part), [
            'purchase_date' => '2026-10-10', 'vendor_name' => '光華資訊', 'quantity' => 3, 'unit_price' => 20,
            'invoice' => UploadedFile::fake()->create('發票.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('parts.show', $part));

        $purchase = PartPurchase::firstOrFail();
        $this->assertSame(3, $part->fresh()->current_stock);
        $this->assertSame(3, $purchase->remaining_quantity);
        $this->assertSame(60, $purchase->totalPrice());
        $this->assertCount(1, $purchase->attachments);
        $this->get(route('attachments.show', $purchase->attachments->first()))->assertOk();
    }

    public function test_the_attachment_shows_as_a_button_and_can_be_removed_without_uploading_a_new_one(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $this->post(route('parts.purchases.store', $part), [
            'purchase_date' => '2026-10-10', 'vendor_name' => '光華資訊', 'quantity' => 1, 'unit_price' => 20,
            'invoice' => UploadedFile::fake()->image('receipt-2026.jpg'),
        ]);
        $purchase = PartPurchase::firstOrFail();
        $path = $purchase->attachments->first()->file_path;
        Storage::disk('local')->assertExists($path);

        // 履歷頁只顯示「附件」按鈕，不顯示檔名；按鈕帶著預覽用的網址與檔案類型。
        $this->get(route('parts.show', $part))
            ->assertSee('data-bs-target="#attachmentModal"', false)
            ->assertSee('data-mime="image/jpeg"', false)
            ->assertDontSee('receipt-2026.jpg');

        $this->put(route('parts.purchases.update', [$part, $purchase]), [
            'purchase_date' => '2026-10-10', 'vendor_name' => '光華資訊', 'quantity' => 1, 'unit_price' => 20, 'remove_invoice' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $purchase->fresh()->attachments);
        Storage::disk('local')->assertMissing($path);
        $this->get(route('parts.show', $part))->assertDontSee('data-bs-target="#attachmentModal"', false);
    }

    public function test_an_unused_purchase_can_be_changed_or_deleted_and_stock_follows(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $purchase = $this->buy($part, '2026-10-10', 3, 20);

        $this->put(route('parts.purchases.update', [$part, $purchase]), [
            'purchase_date' => '2026-10-11', 'vendor_name' => '原價屋', 'quantity' => 5, 'unit_price' => 22,
        ])->assertRedirect(route('parts.show', $part));
        $this->assertSame(5, $part->fresh()->current_stock);
        $this->assertSame(5, $purchase->fresh()->remaining_quantity);

        $this->delete(route('parts.purchases.destroy', [$part, $purchase]))->assertRedirect(route('parts.show', $part));
        $this->assertSame(0, $part->fresh()->current_stock);
        $this->assertDatabaseCount('part_purchases', 0);
    }

    public function test_a_used_purchase_keeps_its_quantity_and_price_and_cannot_be_deleted(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $purchase = $this->buy($part, '2026-10-10', 3, 20);
        app(PartStockService::class)->consume($part, 1);

        $this->put(route('parts.purchases.update', [$part, $purchase]), [
            'purchase_date' => '2026-10-10', 'vendor_name' => '測試廠商', 'quantity' => 9, 'unit_price' => 20,
        ])->assertSessionHas('error');
        $this->assertSame(3, $purchase->fresh()->quantity);

        // 廠商、備註仍然可以修改。
        $this->put(route('parts.purchases.update', [$part, $purchase]), [
            'purchase_date' => '2026-10-10', 'vendor_name' => '新廠商', 'quantity' => 3, 'unit_price' => 20, 'note' => '補登',
        ])->assertSessionHasNoErrors();
        $this->assertSame('新廠商', $purchase->fresh()->vendor_name);

        $this->delete(route('parts.purchases.destroy', [$part, $purchase]))->assertSessionHas('error');
        $this->assertDatabaseCount('part_purchases', 1);
    }

    // ---------- 先進先出 ----------

    public function test_usage_cost_follows_first_in_first_out(): void
    {
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);
        $this->buy($part, '2026-10-12', 2, 30);
        $stock = app(PartStockService::class);

        // 10/15 用 1 條 → 20 元
        $first = $stock->consume($part, 1);
        $this->assertSame(20, array_sum(array_map(fn ($a) => $a['quantity'] * $a['unit_price'], $first)));

        // 10/17 用 2 條 → 10/10 剩下的 1 條（20）＋10/12 的 1 條（30）＝50 元，分成兩筆
        $second = $stock->consume($part, 2);
        $this->assertCount(2, $second);
        $this->assertSame(50, array_sum(array_map(fn ($a) => $a['quantity'] * $a['unit_price'], $second)));

        $this->assertSame(1, $part->fresh()->current_stock);
        $this->assertSame(1, (int) $part->purchases()->sum('remaining_quantity'));
    }

    public function test_opening_stock_becomes_the_first_batch_with_its_unit_price(): void
    {
        $part = Part::create(['name' => '網路線', 'unit_price' => 100, 'current_stock' => 4, 'safety_stock' => 1]);

        $opening = $part->purchases()->firstOrFail();
        $this->assertSame('期初庫存', $opening->note);
        $this->assertSame(4, $opening->remaining_quantity);
        $this->assertSame(100, $opening->unit_price);
    }

    public function test_using_more_than_the_stock_is_refused(): void
    {
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 1, 20);

        $this->expectException(DomainException::class);
        app(PartStockService::class)->consume($part, 2);
    }

    // ---------- 保養用料 ----------

    public function test_maintenance_result_can_record_parts_and_they_appear_in_the_part_profile(): void
    {
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);
        $order = $this->makeOpenOrder($this->makeDevice(), 0);

        $technician = $this->makeUserWithPermissions(['maintenance.view', 'maintenance.report'], ['name' => '王小明']);
        $this->actingAs($technician)
            ->post(route('maintenance-orders.results.store', $order), [
                'result' => 'ok',
                'executed_at' => '2026-10-15 10:00',
                'part_usages' => [['part_id' => $part->id, 'quantity' => 1]],
            ])->assertRedirect();

        $this->assertSame(1, $part->fresh()->current_stock);
        $usage = MaintenancePartUsage::firstOrFail();
        $this->assertSame(20, $usage->unit_price);

        $this->actingAs($this->makeUserWithPermissions(['parts.manage']))
            ->get(route('parts.show', $part))
            ->assertSee('保養')
            ->assertSee('保養工單 #' . $order->id)
            ->assertSee('王小明')
            ->assertSee('2026-10-15');
    }

    public function test_maintenance_result_is_not_saved_when_a_part_is_out_of_stock(): void
    {
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 1, 20);
        $order = $this->makeOpenOrder($this->makeDevice(), 0);

        $this->actingAs($this->makeUserWithPermissions(['maintenance.view', 'maintenance.report']))
            ->post(route('maintenance-orders.results.store', $order), [
                'result' => 'ok',
                'executed_at' => '2026-10-15 10:00',
                'part_usages' => [['part_id' => $part->id, 'quantity' => 5]],
            ])->assertSessionHasErrors('part_usages.0.quantity');

        $this->assertNull($order->fresh()->result);
        $this->assertSame(1, $part->fresh()->current_stock);
    }

    // ---------- 刪除零件 ----------

    public function test_a_used_part_cannot_be_deleted_and_shows_a_message_instead_of_an_error_page(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);
        $order = $this->makeOpenOrder($this->makeDevice(), 0);
        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ok', 'executed_at' => '2026-10-15 10:00', 'part_usages' => [['part_id' => $part->id, 'quantity' => 1]],
        ]);

        $this->delete(route('parts.destroy', $part))->assertRedirect(route('parts.show', $part))->assertSessionHas('error');
        $this->assertNotNull($part->fresh());
    }

    public function test_an_unused_part_is_deleted_with_its_purchases(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);

        $this->delete(route('parts.destroy', $part))->assertRedirect(route('parts.index'));
        $this->assertDatabaseCount('parts', 0);
        $this->assertDatabaseCount('part_purchases', 0);
    }
}
