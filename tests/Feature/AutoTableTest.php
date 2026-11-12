<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 全站每一個表格／清單：螢幕夠大用表格、不夠大用清單，隨畫面自動切換（layouts/partials/auto-table）。
 * 切換是瀏覽器端的程式，實際切換由真實瀏覽器測試驗證；這裡只管頁面接線：
 * 每個列表頁都載入自動切換；主檔的表格沒有被標成「不轉清單」（data-no-onion）。
 */
class AutoTableTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 有表格／清單的列表頁（管理員都看得到）。 */
    private const LIST_ROUTES = [
        'repairs.index', 'users.index', 'roles.index', 'departments.index', 'classrooms.index', 'device-categories.index', 'devices.index',
        'parts.index', 'maintenance-orders.index', 'maintenance-plans.index', 'maintenance-items.index', 'knowledge-base.index',
        'reservations.index', 'audit-logs.index', 'device-profile.index', 'repair-cost-analysis.index',
    ];

    public function test_every_list_page_switches_between_table_and_list_by_screen_size(): void
    {
        $this->loginAsAnyUser();

        foreach (self::LIST_ROUTES as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('MIN_WIDTH', $html, "$route 沒有表格／清單自動切換");
            $this->assertStringContainsString('MIN_RATIO', $html, $route);
        }
    }

    public function test_master_data_tables_are_not_exempted_from_the_list_view(): void
    {
        $this->loginAsAnyUser();

        // 主檔的表格窄螢幕要能變成清單，所以不能標 data-no-onion。
        // （只有匯出預覽用的表格刻意標了 data-no-onion：預覽必須維持表格，見 parts/_export-modal、exports/_sections-modal；
        //   洋蔥元件的說明文字裡也有這個字，所以只檢查 <table> 標籤本身。）
        foreach (['users.index', 'roles.index', 'departments.index', 'classrooms.index', 'device-categories.index', 'devices.index'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<table[^>]*data-no-onion/', $html, "$route 的表格不該被標成不轉清單（窄螢幕要用清單）");
        }
    }

    public function test_auto_switch_is_loaded_once_after_the_list_onion_component(): void
    {
        $this->loginAsAnyUser();
        $html = $this->get(route('users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'var MIN_WIDTH'), '自動切換腳本只能載入一次');
        // 清單要先由洋蔥元件建好，自動切換才有東西可以切。
        $this->assertLessThan(strpos($html, 'var MIN_WIDTH'), strpos($html, "var KEY = 'onionListMode'"));
    }
}
