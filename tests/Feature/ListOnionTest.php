<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 清單的洋蔥式檢視（layouts/partials/list-onion.blade.php）：
 * 列表頁都載入了這個功能，而且原本的表格（所有欄位與操作按鈕）仍然在網頁裡，沒有任何功能被拿掉。
 * 用示範資料測，這樣每個列表頁都有資料、都會畫出表格。
 */
class ListOnionTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public static function listPages(): array
    {
        return [
            '用戶' => ['users.index', true],
            '身分' => ['roles.index', true],
            '教室' => ['classrooms.index', true],
            '設備' => ['devices.index', true],
            '報修' => ['repairs.index', true],
            '保養工單' => ['maintenance-orders.index', true],
            // 操作紀錄在示範資料裡可能是空的（空的時候只顯示「沒有資料」，不畫表格），所以不強制要有表格。
            '操作紀錄' => ['audit-logs.index', false],
        ];
    }

    #[DataProvider('listPages')]
    public function test_list_pages_load_the_onion_list_and_keep_the_original_table(string $route, bool $needsTable): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('onionListMode', $html, "$route 沒有載入清單的洋蔥式檢視");

        if ($needsTable || str_contains($html, '<table')) {
            $this->assertStringContainsString('<table', $html, "$route 原本的表格不見了");
            $this->assertStringContainsString('table-responsive', $html, "$route 的表格外框不見了");
        }
    }
}
