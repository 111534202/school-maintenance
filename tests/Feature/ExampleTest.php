<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Laravel 預設附的範例測試：網站首頁（網址 /）現在直接導向登入頁，所以確認的是這個導向。
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_home_page_redirects_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
