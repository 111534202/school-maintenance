<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

// 網站首頁：沒登入導到登入頁，已登入導到主控台（不再顯示 Laravel 預設的歡迎頁）。
class ExampleTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_home_page_sends_guests_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_home_page_sends_signed_in_users_to_the_dashboard(): void
    {
        $this->loginAsAnyUser();

        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
