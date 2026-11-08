<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartPurchase;
use App\Models\PartScrap;
use App\Services\PartStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 零件報廢：沒用就壞掉、過期或遺失的零件登記報廢，從庫存扣掉（先進先出），報廢損失依當時批次單價；
 * 登記錯了可以取消報廢，數量加回原本的批次。
 */
class PartScrapTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makePart(): Part
    {
        return Part::create(['name' => 'HDMI 線', 'specification' => '2公尺', 'unit' => '條', 'current_stock' => 0, 'safety_stock' => 1]);
    }

    private function buy(Part $part, string $date, int $quantity, int $unitPrice): PartPurchase
    {
        return app(PartStockService::class)->receive($part, [
            'purchase_date' => $date, 'vendor_name' => '測試廠商', 'quantity' => $quantity, 'unit_price' => $unitPrice, 'note' => null,
        ]);
    }

    public function test_scrapping_takes_the_oldest_batches_first_and_records_the_loss(): void
    {
        $user = $this->loginAsAnyUser();
        $part = $this->makePart();
        $old = $this->buy($part, '2026-10-01', 2, 20);
        $new = $this->buy($part, '2026-10-10', 3, 30);

        $this->post(route('parts.scraps.store', $part), [
            'scrap_date' => '2026-11-01', 'quantity' => 3, 'reason' => '損壞', 'note' => '接頭斷裂',
        ])->assertRedirect(route('parts.show', $part))->assertSessionHas('success');

        $this->assertSame(2, $part->fresh()->current_stock);
        $this->assertSame(0, $old->fresh()->remaining_quantity);
        $this->assertSame(2, $new->fresh()->remaining_quantity);

        $scrap = PartScrap::with('batches')->sole();
        $this->assertSame(3, $scrap->quantity);
        $this->assertSame($user->id, $scrap->recorded_by);
        $this->assertCount(2, $scrap->batches);
        $this->assertSame(2 * 20 + 1 * 30, $scrap->lossAmount());

        // 用過（含報廢）的批次不能刪除，也不能改數量。
        $this->assertTrue($old->fresh()->isUsed());

        $this->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('報廢紀錄')
            ->assertSee('損壞')
            ->assertSee('接頭斷裂')
            ->assertSee('共報廢 3 條')
            ->assertDontSee('共報廢 3 條，損失');
    }

    public function test_scrapping_more_than_the_stock_or_without_a_reason_is_refused(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-01', 2, 20);

        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'quantity' => 3, 'reason' => '損壞'])
            ->assertSessionHasErrors(['quantity' => '報廢數量不能超過目前庫存（2 條）。']);
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'quantity' => 1, 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => now()->addDay()->toDateString(), 'quantity' => 1, 'reason' => '損壞'])
            ->assertSessionHasErrors('scrap_date');

        $this->assertSame(2, $part->fresh()->current_stock);
        $this->assertSame(0, PartScrap::count());
    }

    public function test_cancelling_a_scrap_puts_the_quantity_back_into_the_same_batches(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $old = $this->buy($part, '2026-10-01', 2, 20);
        $new = $this->buy($part, '2026-10-10', 3, 30);
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'quantity' => 3, 'reason' => '過期']);
        $scrap = PartScrap::sole();

        $this->delete(route('parts.scraps.destroy', [$part, $scrap]))
            ->assertRedirect(route('parts.show', $part))->assertSessionHas('success');

        $this->assertSame(5, $part->fresh()->current_stock);
        $this->assertSame(2, $old->fresh()->remaining_quantity);
        $this->assertSame(3, $new->fresh()->remaining_quantity);
        $this->assertSame(0, PartScrap::count());
        $this->assertDatabaseCount('part_scrap_batches', 0);
    }

    public function test_a_part_with_scraps_cannot_be_deleted_and_a_scrap_of_another_part_is_not_found(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $other = Part::create(['name' => '網路線', 'unit' => '條', 'current_stock' => 0, 'safety_stock' => 0]);
        $this->buy($part, '2026-10-01', 2, 20);
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'quantity' => 1, 'reason' => '遺失']);
        $scrap = PartScrap::sole();

        $this->delete(route('parts.destroy', $part))->assertSessionHas('error');
        $this->assertNotNull($part->fresh());

        $this->delete(route('parts.scraps.destroy', [$other, $scrap]))->assertNotFound();
        $this->assertSame(1, PartScrap::count());
    }

    /** 成本分析報表：預設一起顯示報廢損失（依報廢日期篩選），也可以選「不顯示」。 */
    public function test_the_cost_analysis_shows_scrap_losses_unless_excluded(): void
    {
        $this->loginAsAnyUser();
        $part = $this->makePart();
        $this->buy($part, '2026-10-01', 5, 20);
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '2026-11-01', 'quantity' => 2, 'reason' => '外觀破損', 'note' => '外皮裂開']);

        $this->get(route('repair-cost-analysis.index'))
            ->assertOk()
            ->assertSee('報廢損失')
            ->assertSee('報廢明細')
            ->assertSee('外觀破損')
            ->assertSee('NT$ 40');

        // 日期範圍外的報廢不算。
        $this->get(route('repair-cost-analysis.index', ['start_date' => '2026-11-02']))
            ->assertOk()->assertDontSee('外觀破損');

        $this->get(route('repair-cost-analysis.index', ['scraps' => 'exclude']))
            ->assertOk()->assertDontSee('報廢明細')->assertDontSee('外觀破損');

        $this->get(route('repair-cost-analysis.index', ['scraps' => 'whatever']))->assertSessionHasErrors('scraps');

        $this->get(route('repair-cost-analysis.export.excel'))->assertOk();
    }

    public function test_the_scrap_form_opens_and_needs_the_parts_permission(): void
    {
        $part = $this->makePart();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('parts.scraps.create', $part))->assertForbidden();

        $this->loginAsAnyUser();
        $this->get(route('parts.scraps.create', $part))->assertOk()->assertSee('登記報廢')->assertSee('目前沒有庫存，不能登記報廢');
    }
}
