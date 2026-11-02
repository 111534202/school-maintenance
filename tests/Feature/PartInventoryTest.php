<?php

namespace Tests\Feature;

use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class PartInventoryTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
    }

    private function makePart(int $currentStock, int $safetyStock): Part
    {
        return Part::create([
            'name' => '測試備品',
            'specification' => '測試規格',
            'unit_price' => 100,
            'current_stock' => $currentStock,
            'safety_stock' => $safetyStock,
        ]);
    }

    public function test_parts_index_shows_low_stock_warning_below_safety_stock(): void
    {
        $this->makePart(2, 3);

        $this->get(route('parts.index'))
            ->assertOk()
            ->assertSee('⚠ 低庫存')
            ->assertSee('目前低庫存品項（1）');
    }

    public function test_parts_index_does_not_show_low_stock_warning_at_safety_stock(): void
    {
        $this->makePart(3, 3);

        $this->get(route('parts.index'))
            ->assertOk()
            // 「查看目前低庫存品項」按鈕一定會出現；這裡檢查的是列表裡的低庫存標籤與彈窗筆數。
            ->assertDontSee('⚠ 低庫存')
            ->assertSee('目前低庫存品項（0）');
    }

    public function test_parts_index_does_not_show_low_stock_warning_above_safety_stock(): void
    {
        $this->makePart(5, 3);

        $this->get(route('parts.index'))
            ->assertOk()
            // 「查看目前低庫存品項」按鈕一定會出現；這裡檢查的是列表裡的低庫存標籤與彈窗筆數。
            ->assertDontSee('⚠ 低庫存')
            ->assertSee('目前低庫存品項（0）');
    }
}