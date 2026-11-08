<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 洋蔥式選單（layouts/partials/nav-drilldown.blade.php，手機抽屜與桌面側邊欄共用）：
 * 分類要遵守權限、只剩一個項目的分類會直接變成第一層連結、目前所在頁面那一層預設展開。
 */
class DrilldownNavigationTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 以某個身分開儀表板，回傳整頁 HTML。 */
    private function page(string $slug, string $route = 'dashboard'): string
    {
        $response = $this->actingAs($this->makeUser($slug))->get(route($route));
        $response->assertOk();

        return $response->getContent();
    }

    /** 取出選單：'drawer' = 漢堡按鈕打開的抽屜（全站唯一的選單）。 */
    private function menu(string $html, string $which): string
    {
        $this->assertSame(1, preg_match('#id="drill-'.$which.'".*?</script>#s', $html, $m), "找不到 $which 選單");

        return $m[0];
    }

    /** 取出選單裡某個分類的第二層面板；沒有這個分類就回傳空字串。 */
    private function panel(string $menu, string $id): string
    {
        if (! preg_match('#data-drill-panel="'.$id.'".*?(?=<div class="drill-panel|</script>)#s', $menu, $m)) {
            return '';
        }

        return $m[0];
    }

    public static function groupMatrix(): array
    {
        // 欄位：身分、有沒有「主檔」分類（null = 不檢查）、有沒有「保養與 AI 預防」分類、保養分類裡有沒有 AI 預防保養
        return [
            'admin 全都有' => ['admin', true, true, true],
            'it_manager 有保養與 AI' => ['it_manager', null, true, true],
            'technician 保養但沒有 AI、沒有主檔' => ['technician', false, true, false],
            'executive 只能看保養' => ['executive', null, true, false],
            'teacher 沒有主檔也沒有保養' => ['teacher', false, false, false],
        ];
    }

    #[DataProvider('groupMatrix')]
    public function test_groups_follow_permissions(string $slug, ?bool $master, bool $maintenance, bool $ai): void
    {
        $html = $this->page($slug);

        foreach (['drawer'] as $which) {
            $menu = $this->menu($html, $which);

            if ($master !== null) {
                $this->assertSame($master, str_contains($menu, 'data-drill-open="master"'), "$slug/$which 主檔分類");
            }
            $this->assertSame($maintenance, str_contains($menu, 'data-drill-open="maintenance"'), "$slug/$which 保養分類");

            if ($maintenance) {
                $sub = $this->panel($menu, 'maintenance');
                $this->assertNotSame('', $sub);
                $this->assertStringContainsString(route('maintenance-orders.index'), $sub);
                $this->assertStringContainsString(route('device-profile.index'), $sub);
                $this->assertSame($ai, str_contains($sub, route('preventive-candidates.index')), "$slug/$which AI 預防保養連結");
            }
        }
    }

    public function test_repair_and_knowledge_base_stay_reachable_for_every_role(): void
    {
        // 簡化選單不能讓原本的功能消失：報修與知識庫對每個身分都要點得到。
        foreach (['admin', 'it_manager', 'technician', 'executive', 'teacher'] as $slug) {
            $menu = $this->menu($this->page($slug), 'drawer');

            $this->assertStringContainsString('href="'.route('repairs.index').'"', $menu, "$slug 找不到報修");
            $this->assertStringContainsString('href="'.route('knowledge-base.index').'"', $menu, "$slug 找不到知識庫");
            $this->assertStringContainsString('href="'.route('dashboard').'"', $menu, "$slug 找不到主控台");
        }
    }

    public function test_group_with_a_single_visible_item_becomes_a_first_level_link(): void
    {
        // 只有「操作紀錄」權限的人：「系統」分類只剩一個項目，直接顯示在第一層，不多點一層。
        $this->actingAs($this->makeUserWithPermissions(['audit-logs.view']));
        $html = $this->get(route('dashboard'))->getContent();

        foreach (['drawer'] as $which) {
            $menu = $this->menu($html, $which);
            $this->assertStringNotContainsString('data-drill-open="system"', $menu);
            $this->assertStringContainsString('href="'.route('audit-logs.index').'"', $menu);
        }
    }

    public function test_the_drawer_menu_appears_once_without_a_fixed_sidebar(): void
    {
        $html = $this->page('admin');

        $this->assertSame(1, substr_count($html, 'id="drill-drawer"'));
        $this->assertStringNotContainsString('id="drill-desktop"', $html);   // 不再有桌面固定側邊欄
        $this->assertStringNotContainsString('class="app-sidebar', $html);
        $this->assertStringContainsString('id="sidebarOffcanvas"', $html);
    }

    // 選單入口在每個螢幕寬度都要有一個：
    //   1200px 以下用漢堡按鈕（不能帶 d-none、d-sm/md/lg-none 這類「比較窄就隱藏」的類別）；
    //   1200px 以上（xl）漢堡按鈕收起來（d-xl-none），改由上方列的下拉選單（tb-topnav，d-xl-flex）提供，而且項目跟漢堡選單一樣。
    public function test_there_is_a_menu_entry_at_every_screen_width(): void
    {
        $html = $this->page('admin');

        $this->assertSame(1, preg_match('/<button[^>]*data-bs-target="#sidebarOffcanvas"[^>]*>/', $html, $button));
        $this->assertDoesNotMatchRegularExpression('/\bd-(sm|md|lg)-none\b|\bd-none\b/', $button[0]);

        if (str_contains($button[0], 'd-xl-none')) {
            $this->assertSame(1, preg_match('/<ul class="navbar-nav tb-topnav[^"]*\bd-xl-flex\b[^"]*"[^>]*>(.*?)<\/ul>\s*<script>/s', $html, $top), '寬螢幕收起漢堡按鈕時，上方列必須有選單');
            foreach (['users.index', 'repairs.index', 'maintenance-orders.index', 'parts.index', 'audit-logs.index'] as $route) {
                $this->assertStringContainsString('href="'.route($route).'"', $top[1], "上方列選單缺少 {$route}");
            }
        }
    }

    // 最內層的清單頁（用戶主檔、空間預約…）也要有「返回」；主控台是最上層，不需要。
    public function test_inner_pages_have_a_back_button_but_the_dashboard_does_not(): void
    {
        $this->assertStringNotContainsString('data-page-back aria-label=', $this->page('admin', 'dashboard'));   // 按鈕本體；頁尾的 script 在每一頁都有

        foreach (['users.index', 'reservations.index', 'classrooms.index', 'repairs.index', 'maintenance-orders.index'] as $route) {
            $html = $this->page('admin', $route);
            $this->assertStringContainsString('data-page-back aria-label=', $html, "{$route} 缺少返回按鈕");
            $this->assertStringContainsString('href="'.route('dashboard').'" class="btn btn-outline-light btn-sm flex-shrink-0" data-page-back', $html);
        }
    }

    // 點頂端列的網站名稱可以回到主控台（任何頁面都一樣；沒登入的畫面只是標題，不是連結）。同步自 integrate/all（5cc43ed）。
    public function test_the_site_title_links_back_to_the_dashboard_when_signed_in(): void
    {
        foreach (['dashboard', 'users.index', 'reservations.index', 'parts.index'] as $route) {
            $this->assertStringContainsString('<a href="'.route('dashboard').'" class="navbar-brand', $this->page('admin', $route), "{$route} 的網站名稱沒有連到主控台");
        }

        $this->get(route('login'))->assertOk()->assertDontSee('<a href="'.route('dashboard').'" class="navbar-brand', false);
    }

    // 頂端列高度固定 56px：上下留白各 0.5rem（Bootstrap 預設），洋蔥的「一個畫面」以這個高度計算清單能放幾筆。
    public function test_the_top_bar_keeps_the_fixed_56px_height(): void
    {
        $css = file_get_contents(public_path('css/theme-b.css'));

        $this->assertStringContainsString('.tb-navbar { padding-top: 0.5rem; padding-bottom: 0.5rem; }', $css);
        $this->assertDoesNotMatchRegularExpression('/\.tb-navbar\s*\{[^}]*padding-(top|bottom):\s*(?!0\.5rem)[0-9.]+rem/', $css, '頂端列的上下留白被改成 0.5rem 以外的值，高度就不是 56px');
    }

    public function test_group_containing_current_page_is_opened_by_default(): void
    {
        $html = $this->page('admin', 'maintenance-orders.index');

        foreach (['drawer'] as $which) {
            $menu = $this->menu($html, $which);
            // 目前在「保養工單」頁，所以「保養與 AI 預防」那一層預設是展開的，第一層被推到旁邊。
            $this->assertMatchesRegularExpression('/class="drill-panel drill-sub is-open" data-drill-panel="maintenance"/', $menu);
            $this->assertStringContainsString('drill-root is-pushed', $menu);
        }
    }

    public function test_dashboard_starts_on_first_level(): void
    {
        $menu = $this->menu($this->page('admin'), 'drawer');

        $this->assertDoesNotMatchRegularExpression('/drill-sub is-open/', $menu);
        $this->assertStringNotContainsString('drill-root is-pushed', $menu);
    }
}
