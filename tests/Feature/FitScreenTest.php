<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 「剛好一個畫面」：主控台壓成一屏（手機版圖表收進「數據圖表」一層），列表頁載入 fit-screen。
 * 這些是網頁結構的檢查；實際的尺寸與換頁行為要開瀏覽器看。
 */
class FitScreenTest extends TestCase
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
            '報修' => ['repairs.index'],
            '用戶' => ['users.index'],
            '設備' => ['devices.index'],
            '保養工單' => ['maintenance-orders.index'],
        ];
    }

    #[DataProvider('listPages')]
    public function test_list_pages_load_fit_screen(string $route): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('fit-pager', $html, "$route 沒有載入「剛好一個畫面」");
        $this->assertStringContainsString('fit-scroll', $html);
    }

    public function test_dashboard_shows_the_two_important_charts_directly(): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'))->assertOk()->getContent();

        // 圖表直接顯示（不再有手機的「數據圖表」滑入層）；只留兩張。
        $this->assertStringContainsString('id="home-charts"', $html);
        $this->assertStringNotContainsString('data-charts-open', $html);
        $this->assertStringNotContainsString('data-charts-back', $html);
        foreach (['工單狀態分布', '近 14 日新增報修'] as $title) {
            $this->assertStringContainsString($title, $html, "圖表「{$title}」不見了");
        }
        // 數字卡片都還在，而且點了還是跳去原本的功能。
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'kpi-card'));
    }

    public function test_teacher_dashboard_has_the_same_two_charts_and_no_master_charts(): void
    {
        $html = $this->actingAs($this->makeUser('teacher'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('工單狀態分布', $html);
        $this->assertStringNotContainsString('用戶身分分布', $html);
    }
}
