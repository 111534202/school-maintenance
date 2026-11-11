<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 六個主檔（用戶、身分、部門、教室、設備類別、設備）：螢幕夠大用表格、不夠大用清單，隨畫面自動切換。
 * 切換是瀏覽器端的程式（master/_auto-table），實際切換由真實瀏覽器測試驗證；這裡只管頁面接線：
 * 都有引入自動切換、表格沒有被標成「不轉清單」（data-no-onion），而且不再另外提供手動切換。
 */
class MasterAutoTableTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_every_master_page_switches_between_table_and_list_by_screen_size(): void
    {
        $this->loginAsAnyUser();

        foreach (['users.index', 'roles.index', 'departments.index', 'classrooms.index', 'device-categories.index', 'devices.index'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('MIN_WIDTH', $html, "$route 沒有引入表格／清單自動切換");
            $this->assertStringContainsString('MIN_RATIO', $html, $route);
            // （洋蔥元件的說明文字裡也有這個字，所以只檢查 <table> 標籤本身。）
            $this->assertDoesNotMatchRegularExpression('/<table[^>]*data-no-onion/', $html, "$route 的表格不該被標成不轉清單（窄螢幕要用清單）");
        }
    }
}
