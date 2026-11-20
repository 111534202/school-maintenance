<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * 全站分頁（resources/views/pagination/tb）：
 * - 電腦版一次列 10 頁「‹ 1 2 … 10 … ›」，在第 11～20 頁時就列 11～20；前後還有頁數時顯示「…」。
 * - 手機版「‹ [12] / 34 ›」，目前頁數放在有外框的輸入框裡，可以直接改成要跳到的頁數。
 */
class PaginationViewTest extends TestCase
{
    private function render(int $page, int $total = 500): string
    {
        $paginator = new LengthAwarePaginator(range(1, 15), $total, 15, $page, ['path' => 'http://localhost/items']);

        return (string) $paginator->links();
    }

    public function test_desktop_lists_ten_pages_per_block_with_ellipsis(): void
    {
        $first = $this->render(1);   // 共 34 頁
        $this->assertStringContainsString('href="http://localhost/items?page=10">10</a>', $first);
        $this->assertStringNotContainsString('?page=11">11</a>', $first);
        $this->assertStringContainsString('href="http://localhost/items?page=11" title="後 10 頁">…</a>', $first);
        $this->assertStringNotContainsString('?page=34">34</a>', $first, '不再列出最後兩頁');
        $this->assertStringContainsString('data-last-page="34"', $first);

        $middle = $this->render(12);
        $this->assertStringContainsString('href="http://localhost/items?page=10" title="前 10 頁">…</a>', $middle);
        $this->assertStringContainsString('?page=20">20</a>', $middle);
        $this->assertStringContainsString('<span class="page-link">12</span>', $middle);
        $this->assertStringContainsString('href="http://localhost/items?page=21" title="後 10 頁">…</a>', $middle);

        $lastBlock = $this->render(34);
        $this->assertStringContainsString('?page=31">31</a>', $lastBlock);
        $this->assertStringNotContainsString('title="後 10 頁"', $lastBlock);
    }

    public function test_mobile_shows_current_over_total_and_can_jump(): void
    {
        $html = $this->render(12);

        $this->assertStringContainsString('value="12" data-current="12"', $html);
        $this->assertStringContainsString('> / 34</span>', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('class="tb-page-jump', $html);
        $this->assertStringContainsString('data-page-name="page"', $html);
    }
}
