<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 寄信設定：管理員在網頁填寄信用的信箱帳號。
 * 重點：只有系統管理員能用；密碼加密存放、畫面與操作紀錄都看不到；密碼留空代表不修改；
 * 啟用後全站寄信改用這組設定；測試信失敗時顯示原因但不帶出密碼。
 */
class MailSettingTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private const SECRET = 'abcd-efgh-ijkl-mnop';

    private function payload(array $override = []): array
    {
        return array_merge([
            'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'smtp_account' => 'school@example.edu', 'password' => self::SECRET,
            'from_address' => 'school@example.edu', 'from_label' => '學校設備維保系統', 'is_enabled' => '1',
        ], $override);
    }

    public function test_only_admin_can_open_and_change_mail_settings(): void
    {
        $this->actingAs($this->makeItManager());   // 設備管理員預設沒有寄信設定權限
        $this->get(route('mail-settings.edit'))->assertForbidden();
        $this->put(route('mail-settings.update'), $this->payload())->assertForbidden();
        $this->post(route('mail-settings.test'), ['recipient' => 'a@example.com'])->assertForbidden();

        $this->actingAs($this->loginAsAnyUser());
        $this->get(route('mail-settings.edit'))->assertOk()->assertSee('Gmail');
    }

    public function test_password_is_stored_encrypted_and_never_shown_or_logged(): void
    {
        $admin = $this->loginAsAnyUser();

        $this->put(route('mail-settings.update'), $this->payload())->assertRedirect(route('mail-settings.edit'))->assertSessionHas('success');

        $setting = MailSetting::current();
        $this->assertSame(self::SECRET, $setting->password);                                       // 讀出來會自動解密
        $this->assertNotSame(self::SECRET, DB::table('mail_settings')->value('password'));         // 資料庫裡是密文
        $this->assertSame($admin->id, $setting->updated_by);

        $this->get(route('mail-settings.edit'))->assertOk()->assertDontSee(self::SECRET);          // 畫面看不到
        $this->assertStringNotContainsString(self::SECRET, json_encode($setting->toArray()));      // 轉成陣列也不帶密碼
        $logs = AuditLog::query()->get()->toJson(JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::SECRET, $logs);                                 // 操作紀錄看不到
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_type' => MailSetting::class]);
    }

    public function test_blank_password_keeps_the_existing_one_and_first_save_requires_it(): void
    {
        $this->loginAsAnyUser();

        // 第一次有填帳號卻沒填密碼：不接受。
        $this->put(route('mail-settings.update'), $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $this->assertNull(MailSetting::current());

        $this->put(route('mail-settings.update'), $this->payload())->assertSessionHasNoErrors();
        // 之後密碼留空 → 沿用原本的，其他欄位照改。
        $this->put(route('mail-settings.update'), $this->payload(['password' => '', 'host' => 'smtp.office365.com']))->assertSessionHasNoErrors();

        $setting = MailSetting::current();
        $this->assertSame(self::SECRET, $setting->password);
        $this->assertSame('smtp.office365.com', $setting->host);
        $this->assertSame(1, MailSetting::count());   // 永遠只有一筆
    }

    public function test_validation_rejects_bad_values(): void
    {
        $this->loginAsAnyUser();

        $this->put(route('mail-settings.update'), $this->payload(['port' => 70000, 'encryption' => 'weird', 'from_address' => 'not-an-email']))
            ->assertSessionHasErrors(['port', 'encryption', 'from_address']);
        $this->assertNull(MailSetting::current());
    }

    public function test_enabled_setting_overrides_mail_config_and_disabled_one_does_not(): void
    {
        $this->loginAsAnyUser();
        config(['mail.default' => 'log']);

        $this->put(route('mail-settings.update'), $this->payload(['is_enabled' => '0']));
        MailSetting::applyEnabledToConfig();
        $this->assertSame('log', config('mail.default'));   // 未啟用：維持原本

        $this->put(route('mail-settings.update'), $this->payload(['port' => 465, 'encryption' => 'ssl']));
        MailSetting::applyEnabledToConfig();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(self::SECRET, config('mail.mailers.smtp.password'));
        $this->assertSame('school@example.edu', config('mail.from.address'));
        $this->assertSame('學校設備維保系統', config('mail.from.name'));
    }

    public function test_missing_table_does_not_break_the_site(): void
    {
        \Illuminate\Support\Facades\Schema::drop('mail_settings');

        MailSetting::applyEnabledToConfig();   // 不能丟出例外

        $this->assertTrue(true);
    }

    public function test_test_mail_needs_saved_settings_and_sends_to_the_recipient(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('mail-settings.test'), ['recipient' => 'me@example.com'])->assertSessionHas('error');

        $this->put(route('mail-settings.update'), $this->payload(['is_enabled' => '0']));
        Mail::fake();
        $this->post(route('mail-settings.test'), ['recipient' => 'me@example.com'])->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail_tested']);
    }

    public function test_failed_test_mail_shows_the_reason_without_the_password(): void
    {
        $this->loginAsAnyUser();
        $this->put(route('mail-settings.update'), $this->payload());

        Mail::shouldReceive('purge')->once();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Authentication failed for ' . self::SECRET . ' on server'));

        $response = $this->post(route('mail-settings.test'), ['recipient' => 'me@example.com']);
        $response->assertSessionHas('error');

        $error = session('error');
        $this->assertStringContainsString('Authentication failed', $error);
        $this->assertStringNotContainsString(self::SECRET, $error);
    }

    public function test_test_mail_requires_a_valid_recipient(): void
    {
        $this->loginAsAnyUser();
        $this->put(route('mail-settings.update'), $this->payload());

        $this->post(route('mail-settings.test'), ['recipient' => 'nope'])->assertSessionHasErrors('recipient');
    }
}
