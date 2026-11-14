<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 錯誤頁：403、404、419、429、500、503 都要是中文、說明發生什麼事、可以回上一頁或回首頁，
 * 不再顯示英文的「This action is unauthorized」「Not Found」「Page Expired」。
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_forbidden_page_is_chinese_and_explains_what_to_do(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('你沒有權限使用這個功能')
            ->assertSee('聯絡資訊組')
            ->assertSee('回到首頁')
            ->assertDontSee('This action is unauthorized');
    }

    public function test_not_found_page_is_chinese(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get('/repairs/999999')
            ->assertNotFound()
            ->assertSee('找不到這個頁面')
            ->assertDontSee('Not Found');
    }

    public function test_a_guest_sees_a_login_button_instead_of_a_home_button(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('前往登入')->assertDontSee('回到首頁');
    }

    public function test_page_expired_page_tells_people_their_text_is_saved_and_links_to_login(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('登入逾時，請重新登入', $html);
        $this->assertStringContainsString((string) config('session.lifetime') . ' 分鐘', $html);
        $this->assertStringContainsString('暫存在這台電腦', $html);
        $this->assertStringContainsString('重新登入', $html);
        $this->assertStringNotContainsString('Page Expired', $html);
    }

    public function test_server_error_too_many_requests_and_maintenance_pages_are_chinese(): void
    {
        foreach ([500 => '系統發生錯誤', 429 => '操作太頻繁', 503 => '系統維護中'] as $code => $title) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString($title, $html, "{$code} 頁面標題");
            $this->assertStringContainsString("錯誤代碼 {$code}", $html);
        }
    }
}
