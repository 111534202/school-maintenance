<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 篩選列的洋蔥式（layouts/partials/filter-onion.blade.php）：
 * 有篩選列的頁面都載入了這個功能，而且原本的篩選表單（欄位名稱、GET 送出）仍然在網頁裡，沒有任何篩選功能被拿掉。
 */
class FilterOnionTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public static function filterPages(): array
    {
        return [
            '報修' => ['repairs.index', ['status', 'classroom_id', 'assignee']],
            '用戶' => ['users.index', []],
            '設備' => ['devices.index', []],
            '教室' => ['classrooms.index', []],
            '身分' => ['roles.index', []],
            '部門' => ['departments.index', []],
        ];
    }

    #[DataProvider('filterPages')]
    public function test_filter_pages_load_filter_onion_and_keep_the_original_form(string $route, array $fields): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('fo-open', $html, "$route 沒有載入篩選列的洋蔥式");
        $this->assertStringContainsString('class="filter-bar"', $html, "$route 原本的篩選表單不見了");

        foreach ($fields as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html, "$route 的篩選欄位 $name 不見了");
        }
    }

    public function test_repairs_filter_still_filters_by_query_string(): void
    {
        $admin = $this->makeUser('admin');

        // 套用了條件的網址：頁面正常顯示，摘要列的「清除」連結（原本的清除篩選連結）也在。
        $html = $this->actingAs($admin)->get(route('repairs.index', ['status' => 'pending', 'assignee' => 'y']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="pending"\s+selected/', $html);
        $this->assertStringContainsString('value="y"', $html);
    }
}
