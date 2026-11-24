<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 左上角的 logo 文字是「校園設備維保電子化系統」（可以調整）；整體專案名稱「校園設備維保系統」不能改（設定檔、信件主旨）。
 * 登入前後的每個畫面左上角都要有 logo，登入後點它要回到主控台。
 */
class SiteTitleTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private const TITLE = '校園設備維保電子化系統';       // logo
    private const PROJECT = '校園設備維保系統';           // 專案名稱

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

    public function test_logo_and_project_name_are_kept_apart(): void
    {
        // logo：畫面左上角的文字。
        $this->assertSame(self::TITLE, __('common.site_title'));

        // 專案名稱：設定檔與信件主旨，不隨 logo 改動。
        $this->assertSame(self::PROJECT, config('app.name'));
        $this->assertStringContainsString(self::PROJECT, __('mail.temporary_password.subject'));
        $this->assertStringContainsString(self::PROJECT, __('mail_settings.test_subject'));
        $this->assertStringNotContainsString('電子化', __('mail.temporary_password.subject'));

        app()->setLocale('en');
        $this->assertSame('Campus Equipment e-Maintenance System', __('common.site_title'));
    }
}
