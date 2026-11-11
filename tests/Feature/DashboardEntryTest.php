<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 主控台不再有「功能入口」（主檔、報修服務、保養管理、空間與零件、系統五個分類卡）：
 * 功能都從左上角的漢堡選單進入（項目與權限見 App\Support\NavMenu，DrilldownNavigationTest 檢查）；
 * 主控台只留數字卡片與兩張重要圖表。
 */
class DashboardEntryTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_dashboard_has_no_function_entry_tiles_for_any_role(): void
    {
        foreach (['admin', 'it_manager', 'technician', 'teacher'] as $slug) {
            $html = $this->actingAs($this->makeUser($slug))->get(route('dashboard'))->assertOk()->getContent();

            $this->assertStringNotContainsString('id="home-entry"', $html, "$slug 的主控台不該有功能入口");
            $this->assertStringNotContainsString('data-entry-open', $html);
            $this->assertStringNotContainsString('entry-tile', $html);
        }
    }

    public function test_the_hamburger_menu_is_still_the_way_in(): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'))->assertOk()->getContent();

        // 漢堡按鈕與抽屜選單還在，抽屜裡有主檔等分類。
        $this->assertStringContainsString('sidebarOffcanvas', $html);
        $this->assertStringContainsString('data-drill-open="master"', $html);
    }

    public function test_dashboard_keeps_its_cards_and_the_two_important_charts(): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('kpi-card', $html);
        $this->assertStringContainsString('chart.umd', $html);
        // 圖表標題與資料（沒有報修資料時只顯示「目前沒有資料」，所以用標題檢查）。
        $this->assertStringContainsString('工單狀態分布', $html);
        $this->assertStringContainsString('近 14 日新增報修', $html);
        foreach (['設備狀態分布', '用戶身分分布', '本月教室報修排行', '本月設備類別報修排行'] as $moved) {
            $this->assertStringNotContainsString($moved, $html, "圖表「{$moved}」已搬進主檔");
        }
    }
}
