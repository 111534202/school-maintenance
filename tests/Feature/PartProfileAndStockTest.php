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
            ->assertRedirect(route('parts.show', $part->fresh()));

        // 網址用零件編號，改編號後網址跟著變（/parts/HDMI-1M）。
        $this->assertSame(url('/parts/HDMI-1M'), route('parts.show', $part->fresh()));
        $this->assertSame('HDMI-1M', $part->fresh()->part_code);
        $this->assertSame(1, $part->fresh()->purchases()->count());
        $this->get(route('parts.show', $part->fresh()))->assertSee('零件履歷：HDMI-1M HDMI 線（1公尺）');
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

    /** 零件編號也不能跟設備主檔的設備編號重複，並說明是跟零件還是設備重複。 */
    public function test_a_code_already_used_as_a_device_code_is_refused_and_says_so(): void
    {
        $this->loginAsAnyUser();
        $this->makeDevice('DEV-0100');
        $this->makePart(['part_code' => 'HDMI-2M']);

        $this->getJson(route('parts.check-code', ['code' => 'dev-0100']))
            ->assertJson(['available' => false])
            ->assertJsonFragment(['message' => '「dev-0100」已經是設備「測試教室 測試電腦」的設備編號，請換一個。']);
        $this->getJson(route('parts.check-code', ['code' => 'HDMI-2M']))
            ->assertJson(['available' => false]);
        $this->assertStringContainsString('零件', $this->getJson(route('parts.check-code', ['code' => 'HDMI-2M']))->json('message'));

        $this->post(route('parts.store'), ['part_code' => 'DEV-0100', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0])
            ->assertSessionHasErrors(['part_code' => '「DEV-0100」已經是設備「測試教室 測試電腦」的設備編號，請換一個。']);
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

        $this->get(route('parts.index'))->assertOk()->assertSee('零件列表')->assertSee('PART-1')->assertSee('PART-2');

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
            ->assertSee('目前低庫存零件')
            ->assertSee('目前低庫存品項（1）')
            ->assertSee('data-modal-frame-href="' . route('parts.summary', $low) . '" data-modal-title="零件庫存"', false)
            ->assertDontSee('data-modal-frame-href="' . route('parts.show', $low) . '"', false)   // 不會直接跳到零件履歷
            ->getContent();

        // 低庫存視窗裡只有 LOW-1；跟列表一樣是表格，依視窗大小自動切換成清單。
        $modal = substr($html, strpos($html, 'id="lowStockModal"'));
        $modal = substr($modal, 0, strpos($modal, 'id="lowStockExportModal"') ?: strlen($modal));
        $this->assertStringContainsString('LOW-1', $modal);
        $this->assertStringNotContainsString('OK-1', $modal);
        $this->assertStringContainsString('tb-table-wrap', $modal);

        // 列表：低庫存顯示「低庫存」，其他顯示「庫存充足」。
        $this->assertStringContainsString('<span class="badge tb-pill tb-pill-danger">⚠ 低庫存</span>', $html);
        $this->assertStringContainsString('<span class="badge tb-pill tb-pill-ok">庫存充足</span>', $html);
    }

    public function test_low_stock_parts_can_be_exported_to_excel(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->loginAsAnyUser();
        $low = $this->makePart(['part_code' => 'LOW-1', 'unit' => '條', 'safety_stock' => 5]);
        $this->buy($low, '2026-10-10', 2, 20);
        $enough = $this->makePart(['part_code' => 'OK-1', 'safety_stock' => 1]);
        $this->buy($enough, '2026-10-10', 9, 20);

        // 低庫存視窗的「匯出 Excel」打開只含低庫存品項的預覽視窗（可勾選欄位與零件）。
        $html = $this->get(route('parts.index'))->assertSee('data-bs-target="#lowStockExportModal"', false)->getContent();
        $preview = substr($html, strpos($html, 'id="lowStockExportModal"'));
        $this->assertStringContainsString('name="ids[]" value="' . $low->id . '"', $preview);
        $this->assertStringNotContainsString('name="ids[]" value="' . $enough->id . '"', $preview);

        // 原本的低庫存 Excel 網址仍可使用。
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
            ->assertSee('Cable Master/HD-1M')
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
        $this->delete("/parts/{$part->part_code}/purchases/{$purchase->id}")->assertStatus(405);

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

    public function test_the_attachment_shows_as_a_button_and_cannot_be_removed_after_submitting(): void
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

        // 履歷頁只顯示「附件」按鈕，不在頁面上直接列出檔名；按鈕帶著預覽用的網址、檔案類型與檔名（打開預覽時顯示在標題旁邊）。
        $this->get(route('parts.show', $part))
            ->assertSee('data-bs-target="#attachmentModal"', false)
            ->assertSee('&quot;mime&quot;:&quot;image\/jpeg&quot;', false)
            ->assertSee('&quot;filename&quot;:&quot;receipt-2026.jpg&quot;', false)
            ->assertDontSeeText('receipt-2026.jpg');

        // 送出後的採購紀錄是定案：附件也不能刪除（沒有修改的網址）。
        $this->put("/parts/{$part->part_code}/purchases/{$purchase->id}", ['remove_invoice' => '1'])->assertStatus(405);
        $this->assertCount(1, $purchase->fresh()->attachments);
        Storage::disk('local')->assertExists($path);
    }

    /** 一筆採購最多 5 個附件、加起來 5 MB 以內（單檔不另外限制）；送出後就定案，不能再改附件。 */
    public function test_a_purchase_keeps_up_to_five_attachments_of_two_megabytes_each(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $base = ['purchase_date' => '2026-10-10', 'vendor_name' => '光華資訊', 'quantity' => 1, 'unit_price' => 20];

        $this->post(route('parts.purchases.store', $part), $base + [
            'invoices' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('b.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $purchase = PartPurchase::firstOrFail();
        $this->assertCount(2, $purchase->attachments);

        // 一次最多 5 個附件、加起來 5 MB 以內。
        $this->post(route('parts.purchases.store', $part), $base + [
            'invoices' => array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.jpg"), range(1, 6)),
        ])->assertSessionHasErrors('invoices');
        $this->post(route('parts.purchases.store', $part), $base + [
            'invoices' => [UploadedFile::fake()->create('big.pdf', 5200, 'application/pdf')],
        ])->assertSessionHasErrors('invoices');
        $this->assertSame(1, PartPurchase::count());

        $this->post(route('parts.purchases.store', $part), $base + [
            'invoices' => array_map(fn ($i) => UploadedFile::fake()->image("m{$i}.jpg"), range(1, 4)),
        ])->assertSessionHasNoErrors();

        // 履歷頁只有一顆「附件」按鈕，4 個附件都在按鈕的資料裡（在預覽視窗左右切換）。
        $html = $this->get(route('parts.show', $part))->getContent();
        $this->assertSame(2, substr_count($html, 'data-bs-target="#attachmentModal"'));   // 兩筆採購各一顆「附件」按鈕
        $this->assertSame(6, substr_count($html, '&quot;url&quot;'));                       // 2 + 4 個附件
        $this->assertStringNotContainsString('附件 2', $html);
    }

    public function test_a_submitted_purchase_is_read_only_it_can_only_be_viewed(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $purchase = $this->buy($part, '2026-10-10', 3, 20);
        $url = "/parts/{$part->part_code}/purchases/{$purchase->id}";

        // 可以查看（唯讀的詳細）。
        $this->get(route('parts.purchases.show', [$part, $purchase]))->assertOk()->assertSee('採購紀錄送出後就定案');
        // 沒有修改、刪除的畫面或網址；庫存與紀錄都不變。
        $this->get($url . '/edit')->assertNotFound();
        $this->put($url, ['purchase_date' => '2026-10-11', 'vendor_name' => '原價屋', 'quantity' => 5, 'unit_price' => 22])->assertStatus(405);
        $this->delete($url)->assertStatus(405);
        $this->assertSame(3, $part->fresh()->current_stock);
        $this->assertSame(3, $purchase->fresh()->quantity);
        // 履歷頁的採購列沒有「修改」「刪除」按鈕。
        $this->get(route('parts.show', $part))->assertOk()->assertDontSee('title="修改"', false)->assertDontSee('title="刪除"', false);
    }

    public function test_the_purchase_form_asks_for_a_second_confirmation_before_submitting(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();

        $html = $this->get(route('parts.purchases.create', $part))->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm-title="確認新增採購"', $html);
        $this->assertStringContainsString('data-confirm-ok="確認新增採購"', $html);
        $this->assertStringContainsString('@vendor_name', $html);
        $this->assertStringContainsString('@sum:quantity*unit_price', $html);   // 確認視窗在單價下面顯示總價
        $this->assertStringContainsString('不能修改或刪除', $html);
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
        $order->update(['status' => 'in_progress', 'assigned_to' => $technician->id]);   // 已確認派工給他
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

        $reporter = $this->makeUserWithPermissions(['maintenance.view', 'maintenance.report']);
        $order->update(['status' => 'in_progress', 'assigned_to' => $reporter->id]);   // 已確認派工給他
        $this->actingAs($reporter)
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
        $user = $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-10', 2, 20);
        $order = $this->makeOpenOrder($this->makeDevice(), 0);
        $order->update(['status' => 'in_progress', 'assigned_to' => $user->id]);
        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ok', 'executed_at' => '2026-10-15 10:00', 'part_usages' => [['part_id' => $part->id, 'quantity' => 1]],
        ]);

        $this->delete(route('parts.destroy', $part))->assertRedirect(route('parts.show', $part))->assertSessionHas('error');
        $this->assertNotNull($part->fresh());
    }

    public function test_a_part_can_only_be_deleted_with_no_stock_and_no_usage_or_scrap_records(): void
    {
        $this->loginAsAnyUser();

        // 還有庫存：不能刪，按鈕灰掉並說明原因。
        $withStock = $this->makePart();
        $this->buy($withStock, '2026-10-10', 2, 20);
        $this->assertStringContainsString('目前還有庫存', (string) $withStock->fresh()->deletionBlocker());
        $this->get(route('parts.show', $withStock))->assertOk()->assertSee('不能刪除：目前還有庫存')->assertDontSee('action="' . route('parts.destroy', $withStock) . '"', false);
        $this->delete(route('parts.destroy', $withStock))->assertSessionHas('error');
        $this->assertNotNull($withStock->fresh());

        // 庫存是 0 但有報廢紀錄：不能刪。
        $scrapped = Part::create(['part_code' => 'SCR-1', 'name' => '報廢過的', 'unit' => '個', 'safety_stock' => 0]);
        $this->buy($scrapped, '2026-10-10', 1, 20);
        app(PartStockService::class)->scrap($scrapped, ['scrap_date' => '2026-10-11', 'quantity' => 1, 'reason' => '損壞'], null);
        $this->assertSame(0, $scrapped->fresh()->current_stock);
        $this->assertStringContainsString('報廢紀錄', (string) $scrapped->fresh()->deletionBlocker());
        $this->delete(route('parts.destroy', $scrapped))->assertSessionHas('error');
        $this->assertNotNull($scrapped->fresh());

        // 沒有庫存、沒有任何紀錄：可以刪，畫面上有刪除表單。
        $empty = Part::create(['part_code' => 'EMP-1', 'name' => '空的', 'unit' => '個', 'safety_stock' => 0]);
        $this->assertNull($empty->deletionBlocker());
        $this->get(route('parts.show', $empty))->assertOk()->assertSee('action="' . route('parts.destroy', $empty) . '"', false);
        $this->delete(route('parts.destroy', $empty))->assertRedirect(route('parts.index'));
        $this->assertNull(Part::find($empty->id));
    }

    public function test_number_inputs_drop_leading_zeros_while_typing(): void
    {
        $this->loginAsAnyUser();

        $html = $this->get(route('parts.create'))->assertOk()->getContent();

        $this->assertStringContainsString("/^0\d/.test(el.value)", $html);   // 全站數字欄位：前面的 0 自動消失
        $this->assertStringContainsString('el.select()', $html);              // 點進顯示 0 的欄位整個選起來
    }

    public function test_a_part_with_no_stock_and_no_records_is_deleted(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();   // 沒有庫存、沒有任何紀錄

        $this->delete(route('parts.destroy', $part))->assertRedirect(route('parts.index'));
        $this->assertDatabaseCount('parts', 0);
    }

    // 零件履歷網址用零件編號；舊的流水號網址仍然打得開；和固定網址重複的名稱不能當編號。
    public function test_part_urls_use_the_part_code(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $part = $this->makePart();

        $this->assertSame(url('/parts/' . $part->part_code), route('parts.show', $part));
        $this->get('/parts/' . $part->part_code)->assertOk()->assertSee($part->name);
        $this->get('/parts/' . $part->id)->assertOk()->assertSee($part->name);
        $this->get('/parts/NO-SUCH-CODE')->assertNotFound();

        $this->put(route('parts.update', $part), ['part_code' => 'create', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1])
            ->assertSessionHasErrors('part_code');
    }
}
