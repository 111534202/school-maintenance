<?php

namespace Tests\Feature;

use App\Support\BackLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 「返回」全站只放在頂端列（layouts/app.blade.php 的 data-page-back）：
 * 1. 沒有上一頁時的去處由 App\Support\BackLink 推算（詳細／新增／編輯頁 → 清單頁，特例另外登記）。
 * 2. 各頁面不准再自己放「返回列表」之類的連結，位置才會統一。
 */
class BackLinkTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 以管理員開某一頁，取出頂端列返回按鈕的網址。 */
    private function backHref(string $route): string
    {
        $html = $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route($route))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<a href="([^"]+)"[^>]*data-page-back/', $html, $match), "{$route} 沒有頂端返回按鈕");

        return html_entity_decode($match[1]);
    }

    /** 組一個已綁定網址的路由，模擬瀏覽器開到那一頁。 */
    private function boundRoute(string $uri, string $name, string $path): Route
    {
        $route = (new Route(['GET'], $uri, []))->name($name);
        $route->bind(Request::create($path));

        return $route;
    }

    public function test_list_pages_go_back_to_the_dashboard(): void
    {
        foreach (['users.index', 'reservations.index', 'classrooms.index', 'repairs.index'] as $route) {
            $this->assertSame(route('dashboard'), $this->backHref($route), $route);
        }
    }

    public function test_create_and_edit_pages_go_back_to_their_list(): void
    {
        $this->assertSame(route('users.index'), $this->backHref('users.create'));
        $this->assertSame(route('classrooms.index'), $this->backHref('classrooms.create'));
        $this->assertSame(route('reservations.index'), $this->backHref('reservations.create'));
    }

    public function test_pages_without_a_derivable_parent_use_the_registered_parent(): void
    {
        $this->assertSame(route('preventive-candidates.index'), $this->backHref('ai-settings.edit'));
    }

    public function test_show_pages_with_parameters_go_back_to_their_parent_page(): void
    {
        $order = $this->boundRoute('maintenance-orders/{maintenanceOrder}/result', 'maintenance-orders.results.show', 'maintenance-orders/7/result');
        $this->assertSame(route('maintenance-orders.show', 7), BackLink::url($order));

        $purchase = $this->boundRoute('parts/{part}/purchases/create', 'parts.purchases.create', 'parts/3/purchases/create');
        $this->assertSame(route('parts.show', 3), BackLink::url($purchase));

        $repairLog = $this->boundRoute('repairs/{repair_request}/repair-logs/create', 'repair-logs.create', 'repairs/9/repair-logs/create');
        $this->assertSame(route('repairs.show', 9), BackLink::url($repairLog));
    }

    public function test_unknown_pages_fall_back_to_the_dashboard(): void
    {
        $this->assertSame(route('dashboard'), BackLink::url(null));
        $this->assertSame(route('dashboard'), BackLink::url($this->boundRoute('somewhere/{id}', 'somewhere.show', 'somewhere/1')));
    }

    // 位置統一：各畫面檔案裡不准再有自己的「返回列表／返回工單…」連結，一律用頂端列的返回。
    public function test_no_view_has_its_own_page_level_back_link(): void
    {
        $banned = '/返回(列表|工單|候選列表|預約列表|看板)|back_to_list|back_to_board/u';

        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertDoesNotMatchRegularExpression($banned, $file->getContents(), $file->getRelativePathname() . ' 有自己的返回連結；返回只放在頂端列（layouts/app.blade.php），父頁在 App\Support\BackLink 設定');
        }
    }
}
