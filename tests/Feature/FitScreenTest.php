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

    public function test_dashboard_puts_charts_in_a_mobile_layer_and_keeps_every_chart(): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="home-charts"', $html);
        $this->assertStringContainsString('data-charts-open', $html);
        $this->assertStringContainsString('data-charts-back', $html);

        foreach (['repairStatus', 'repairTrend', 'deviceStatus', 'roleDistribution'] as $chart) {
            $this->assertStringContainsString('id="chart-'.$chart.'"', $html, "圖表 $chart 不見了");
        }
        // 六張數字卡片都還在，而且點了還是跳去原本的功能。
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'kpi-card'));
    }

    public function test_teacher_dashboard_still_has_the_charts_layer(): void
    {
        $html = $this->actingAs($this->makeUser('teacher'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-charts-open', $html);
        $this->assertStringNotContainsString('id="chart-roleDistribution"', $html);
    }
}
