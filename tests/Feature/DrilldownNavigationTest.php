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

    /** 取出某一份選單：'desktop' = 桌面側邊欄、'mobile' = 手機抽屜。 */
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
    public function test_groups_follow_permissions_on_both_desktop_and_mobile(string $slug, ?bool $master, bool $maintenance, bool $ai): void
    {
        $html = $this->page($slug);

        foreach (['desktop', 'mobile'] as $which) {
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
            $menu = $this->menu($this->page($slug), 'desktop');

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

        foreach (['desktop', 'mobile'] as $which) {
            $menu = $this->menu($html, $which);
            $this->assertStringNotContainsString('data-drill-open="system"', $menu);
            $this->assertStringContainsString('href="'.route('audit-logs.index').'"', $menu);
        }
    }

    public function test_both_menus_exist_with_unique_ids(): void
    {
        $html = $this->page('admin');

        $this->assertSame(1, substr_count($html, 'id="drill-desktop"'));
        $this->assertSame(1, substr_count($html, 'id="drill-mobile"'));
        $this->assertStringContainsString('id="sidebarOffcanvas"', $html);
    }

    public function test_group_containing_current_page_is_opened_by_default(): void
    {
        $html = $this->page('admin', 'maintenance-orders.index');

        foreach (['desktop', 'mobile'] as $which) {
            $menu = $this->menu($html, $which);
            // 目前在「保養工單」頁，所以「保養與 AI 預防」那一層預設是展開的，第一層被推到旁邊。
            $this->assertMatchesRegularExpression('/class="drill-panel drill-sub is-open" data-drill-panel="maintenance"/', $menu);
            $this->assertStringContainsString('drill-root is-pushed', $menu);
        }
    }

    public function test_dashboard_starts_on_first_level(): void
    {
        $menu = $this->menu($this->page('admin'), 'desktop');

        $this->assertDoesNotMatchRegularExpression('/drill-sub is-open/', $menu);
        $this->assertStringNotContainsString('drill-root is-pushed', $menu);
    }
}
