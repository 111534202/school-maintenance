<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 系統名稱：登入前後的每個畫面左上角都要有「校園設備維保電子化管理系統」，登入後點它要回到主控台。
 */
class SiteTitleTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private const TITLE = '校園設備維保電子化管理系統';

    public function test_the_title_is_shown_on_the_login_page_and_the_old_names_are_gone(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(self::TITLE)->assertDontSee('學校設備維保');
        $this->get(route('password.request'))->assertOk()->assertSee(self::TITLE);
    }

    public function test_the_title_in_the_top_bar_links_back_to_the_dashboard_for_every_signed_in_page(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        foreach (['dashboard', 'repairs.index', 'repairs.create', 'reservations.index', 'knowledge-base.index'] as $name) {
            $html = $this->get(route($name))->assertOk()->getContent();

            // 左上角的標題是一個連回主控台的連結（data-site-title 是畫面上辨識標題的標記）。
            $this->assertMatchesRegularExpression(
                '#<a href="' . preg_quote(route('dashboard'), '#') . '"[^>]*data-site-title[^>]*>\s*' . self::TITLE . '\s*</a>#u',
                $html,
                "{$name} 的標題應該是連回主控台的連結"
            );
        }
    }

    public function test_the_title_and_mail_texts_use_the_new_name_in_both_languages(): void
    {
        $this->assertSame(self::TITLE, __('common.site_title'));
        $this->assertStringContainsString(self::TITLE, __('mail.temporary_password.subject'));
        $this->assertStringContainsString(self::TITLE, __('mail_settings.test_subject'));
        $this->assertSame(self::TITLE, config('app.name'));

        app()->setLocale('en');
        $this->assertSame('Campus Equipment Maintenance Management System', __('common.site_title'));
    }
}
