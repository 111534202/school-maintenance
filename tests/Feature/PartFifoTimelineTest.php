<?php

namespace Tests\Feature;

use App\Exceptions\PartStockTimelineException;
use App\Models\Part;
use App\Models\PartScrap;
use App\Services\PartStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 依日期（與時間）重新計算先進先出：補登較早的紀錄會扣當時最早的批次，後面的自動順延；
 * 會讓某一筆在當時庫存不足就整個擋下，資料不變。
 */
class PartFifoTimelineTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private PartStockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(PartStockService::class);
    }

    private function part(): Part
    {
        return Part::create(['part_code' => 'HDMI-2', 'name' => 'HDMI 線', 'specification' => '2公尺', 'unit' => '條', 'current_stock' => 0, 'safety_stock' => 1]);
    }

    private function buy(Part $part, string $date, int $qty, int $price): void
    {
        $this->stock->receive($part, ['purchase_date' => $date, 'vendor_name' => '五金行', 'quantity' => $qty, 'unit_price' => $price, 'note' => null]);
    }

    private function scrap(Part $part, string $date, string $time, int $qty): PartScrap
    {
        return $this->stock->scrap($part, ['scrap_date' => $date, 'scrap_time' => $time, 'quantity' => $qty, 'reason' => '損壞'], null);
    }

    /** 批次扣法：[[單價, 數量], ...] */
    private function batches(PartScrap $scrap): array
    {
        return $scrap->fresh('batches')->batches->map(fn ($b) => [(int) $b->unit_price, (int) $b->quantity])->all();
    }

    public function test_a_backdated_scrap_takes_the_earliest_batch_and_later_records_shift(): void
    {
        $part = $this->part();
        $this->buy($part, '2026-11-01', 10, 150);
        $this->buy($part, '2026-11-02', 6, 10);
        $a = $this->scrap($part, '2026-11-02', '10:00', 10);   // 原本扣 11/1 的 10 條
        $b = $this->scrap($part, '2026-11-03', '10:00', 2);    // 原本扣 11/2 的 2 條
        $this->assertSame([[150, 10]], $this->batches($a));

        // 補登 11/1 的報廢 4 條：要扣 11/1 那批（150 元），損失 600 元。
        $late = $this->scrap($part, '2026-11-01', '15:00', 4);

        $this->assertSame([[150, 4]], $this->batches($late));
        $this->assertSame(600, $late->fresh('batches')->lossAmount());
        $this->assertSame([[150, 6], [10, 4]], $this->batches($a));   // 後面的順延
        $this->assertSame([[10, 2]], $this->batches($b));
        $this->assertSame(0, (int) $part->fresh()->current_stock);
        $this->assertSame([0, 0], $part->purchases()->orderBy('purchase_date')->pluck('remaining_quantity')->map(fn ($q) => (int) $q)->all());
    }

    public function test_same_day_records_are_ordered_by_time_not_by_entry_order(): void
    {
        $part = $this->part();
        $this->buy($part, '2026-11-01', 2, 100);
        $this->buy($part, '2026-11-01', 2, 300);
        $afternoon = $this->scrap($part, '2026-11-02', '15:00', 2);
        $morning = $this->scrap($part, '2026-11-02', '08:00', 2);   // 後登記、但時間比較早

        $this->assertSame([[100, 2]], $this->batches($morning));
        $this->assertSame([[300, 2]], $this->batches($afternoon));
    }

    public function test_a_change_that_leaves_a_record_short_at_its_date_is_blocked_and_nothing_changes(): void
    {
        $part = $this->part();
        $this->buy($part, '2026-11-01', 10, 150);
        $this->buy($part, '2026-11-02', 4, 10);
        $this->buy($part, '2026-11-09', 3, 20);
        $a = $this->scrap($part, '2026-11-02', '10:00', 10);
        $this->scrap($part, '2026-11-03', '10:00', 2);
        $before = $this->batches($a);
        $stockBefore = (int) $part->fresh()->current_stock;

        try {
            // 11/1 報廢 4 條後，11/3 那筆只剩 11/1～11/3 買進的 14 − 4 − 10 = 0 條可扣 → 當時庫存不足。
            $this->scrap($part, '2026-11-01', '15:00', 4);
            $this->fail('應該要被擋下');
        } catch (PartStockTimelineException $e) {
            $this->assertStringStartsWith('錯誤，請重新確認：', $e->getMessage());
            $this->assertStringContainsString('2026-11-03 的報廢當時庫存不足', $e->getMessage());
        }

        $this->assertSame(2, PartScrap::count());
        $this->assertSame($before, $this->batches($a));
        $this->assertSame($stockBefore, (int) $part->fresh()->current_stock);
    }

    public function test_the_scrap_form_shows_the_error_as_a_popup(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $part = $this->part();
        $this->buy($part, '2026-11-01', 10, 150);
        $this->buy($part, '2026-11-09', 4, 10);
        $this->scrap($part, '2026-11-02', '10:00', 10);

        // 送出後導回表單，畫面會跳出「錯誤，請重新確認」視窗。
        $this->followingRedirects()
            ->from(route('parts.scraps.create', $part))
            ->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'scrap_time' => '09:00', 'quantity' => 2, 'reason' => '損壞'])
            ->assertSee('id="stockTimelineDialog"', false)
            ->assertSee('錯誤，請重新確認')
            ->assertSee('這樣登記會導致 2026-11-02 的報廢當時庫存不足');
        $this->assertSame(1, PartScrap::count());
    }

    public function test_opening_stock_counts_as_available_from_the_start(): void
    {
        $part = Part::create(['name' => '期初零件', 'unit' => '個', 'current_stock' => 5, 'unit_price' => 30, 'safety_stock' => 1]);
        $scrap = $this->scrap($part, '2026-01-01', '09:00', 2);   // 比零件建立還早，也能扣期初庫存

        $this->assertSame([[30, 2]], $this->batches($scrap));
        $this->assertSame(3, (int) $part->fresh()->current_stock);
    }

    public function test_the_one_time_command_fixes_records_entered_out_of_order(): void
    {
        $part = $this->part();
        $this->buy($part, '2026-11-01', 10, 150);
        $this->buy($part, '2026-11-02', 6, 10);
        $a = $this->scrap($part, '2026-11-02', '10:00', 10);
        $late = $this->scrap($part, '2026-11-01', '15:00', 4);
        // 模擬以前的扣法（依登記先後）：補登的那筆扣到 11/2 的批次。
        $late->batches()->update(['part_purchase_id' => $part->purchases()->whereDate('purchase_date', '2026-11-02')->value('id'), 'unit_price' => 10]);

        $this->artisan('parts:rebuild-fifo')->expectsOutputToContain('已重新計算 1 個零件')->assertExitCode(0)->run();

        $this->assertSame([[150, 4]], $this->batches($late));
        $this->assertSame([[150, 6], [10, 4]], $this->batches($a));
    }

    // 沒有任何批次明細的報廢紀錄（資料不完整）不能讓整個重算出現 500 錯誤：略過它，其他紀錄照常重算。
    public function test_a_scrap_without_batch_rows_is_skipped_instead_of_crashing_the_rebuild(): void
    {
        $part = $this->part();
        $this->buy($part, '2026-11-01', 10, 100);
        PartScrap::create(['part_id' => $part->id, 'scrap_date' => '2026-11-02', 'quantity' => 1, 'reason' => '資料不完整的舊紀錄']);

        $this->assertSame([], $this->stock->rebuild($part));
        $scrap = $this->scrap($part, '2026-11-03', '10:00', 2);   // 正常的報廢之後仍然可以登記
        $this->assertSame(2, (int) $scrap->batches->sum('quantity'));
    }
}
