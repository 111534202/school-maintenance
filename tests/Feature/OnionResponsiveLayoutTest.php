<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 「洋蔥式」響應式版面：手機上表格變卡片、篩選與有標題的區塊可收合（行為在 public/js/onion.js，樣式在 public/css/onion.css）。
 * PHP 測試只守得住「資源有被載入、文字有走語言檔、表格有帶欄位提示」；實際在手機寬度的行為用瀏覽器驗證。
 */
class OnionResponsiveLayoutTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    // 樣式與腳本檔都存在，而且有被共用版面載入（登入頁以外的頁面）。
    public function test_layout_loads_the_onion_assets(): void
    {
        $this->assertFileExists(public_path('css/onion.css'));
        $this->assertFileExists(public_path('js/onion.js'));

        $this->loginAsAnyUser();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('css/onion.css', $html);
        $this->assertStringContainsString('js/onion.js', $html);
    }

    // 按鈕文字由 <body> 的 data 屬性提供，中英文都走語言檔。
    public function test_button_labels_follow_the_language_files(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('dashboard'))
            ->assertSee('data-onion-filter="篩選"', false)
            ->assertSee('data-onion-details="詳情"', false)
            ->assertSee('data-onion-collapse="收合"', false);

        $this->withSession(['locale' => 'en'])->get(route('dashboard'))
            ->assertSee('data-onion-filter="Filter"', false)
            ->assertSee('data-onion-details="Details"', false)
            ->assertSee('data-onion-collapse="Collapse"', false);
    }

    // 列表頁的表格都帶著「哪些欄位放第一層」的提示。直接檢查畫面檔（列表沒有資料時不會畫出表格，用 HTTP 測不到）。
    public function test_list_tables_declare_which_columns_stay_on_the_first_layer(): void
    {
        foreach (['repairs', 'classrooms', 'devices', 'users', 'audit-logs', 'departments', 'roles', 'parts', 'maintenance_orders', 'maintenance_plans', 'preventive_candidates', 'device_profile', 'device-categories', 'reservations'] as $folder) {
            $view = file_get_contents(resource_path("views/{$folder}/index.blade.php"));
            $this->assertMatchesRegularExpression('/<table[^>]*data-onion-primary="[\d,]+"/', $view, "{$folder}/index 的表格缺少 data-onion-primary");
        }
    }

    // 腳本與樣式的關鍵約定沒被改掉：只在窄螢幕生效、桌面版的按鈕預設隱藏。
    public function test_stylesheet_only_changes_the_layout_on_narrow_screens(): void
    {
        $css = file_get_contents(public_path('css/onion.css'));

        $this->assertStringContainsString('.onion-toggle, .onion-filter-toggle, .onion-chevron { display: none; }', $css);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $css);
        // 所有「會改變版面」的規則都必須放在窄螢幕的 @media 裡面：拿掉註解後，@media 之前只能剩下那一行預設隱藏。
        $withoutComments = preg_replace('#/\*.*?\*/#s', '', $css);
        $beforeMedia = trim(substr($withoutComments, 0, strpos($withoutComments, '@media')));
        $this->assertSame('.onion-toggle, .onion-filter-toggle, .onion-chevron { display: none; }', $beforeMedia);
    }
}
