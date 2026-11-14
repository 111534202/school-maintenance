<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 忘記密碼：用註冊帳號時的 Email 收臨時密碼 → 用臨時密碼登入 → 一定要先設定新密碼。
 */
class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function user(array $attrs = []): User
    {
        return $this->makeUserWithRole('teacher', '教師', $attrs + ['account' => 'teacher_a', 'email' => 'teacher.a@example.com', 'password' => 'OldPassword1']);
    }

    private function sentTemporaryPassword(): string
    {
        $password = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use (&$password) {
            $password = $mail->temporaryPassword;

            return true;
        });

        return $password;
    }

    public function test_the_login_page_links_to_the_forgot_password_form(): void
    {
        $this->get(route('login'))->assertSee('忘記密碼？')->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('註冊時的 Email');
    }

    public function test_a_registered_email_gets_a_temporary_password_that_is_stored_hashed_and_expires_in_30_minutes(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => 'TEACHER.A@example.com'])   // 大小寫不同也找得到
            ->assertRedirect(route('login'))->assertSessionHas('status');

        $temporary = $this->sentTemporaryPassword();
        Mail::assertSent(TemporaryPasswordMail::class, fn ($mail) => $mail->hasTo('teacher.a@example.com'));
        $this->assertSame(10, strlen($temporary));
        $user->refresh();
        $this->assertNotSame($temporary, $user->getRawOriginal('temporary_password'));   // 資料庫裡不是明碼
        $this->assertTrue(Hash::check($temporary, $user->temporary_password));
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($user->temporary_password_expires_at, false), 1);
        $this->assertTrue(Hash::check('OldPassword1', $user->password));                  // 原本的密碼沒被蓋掉
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'loggable_id' => $user->id]);
    }

    public function test_the_response_is_the_same_for_unknown_and_inactive_emails_and_nothing_is_sent(): void
    {
        Mail::fake();
        $this->user(['is_active' => false]);

        $known = $this->post(route('password.email'), ['email' => 'teacher.a@example.com']);
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $this->assertSame(session('status'), $known->getSession()->get('status'));
        $unknown->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        Mail::assertNothingSent();   // 停用的帳號、不存在的 Email 都不寄
    }

    public function test_the_same_email_can_only_ask_three_times_an_hour(): void
    {
        Mail::fake();
        RateLimiter::clear('forgot-password|teacher.a@example.com');
        $this->user();

        foreach (range(1, 5) as $i) {
            $this->post(route('password.email'), ['email' => 'teacher.a@example.com'])->assertRedirect(route('login'));
        }

        Mail::assertSent(TemporaryPasswordMail::class, 3);
    }

    public function test_an_invalid_email_is_rejected_with_a_message(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_a_failed_mail_cancels_the_temporary_password_but_looks_the_same(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP 連線失敗'));
        $user = $this->user();

        $this->post(route('password.email'), ['email' => 'teacher.a@example.com'])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertNull($user->fresh()->temporary_password);   // 沒寄出去的臨時密碼不留著
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'description' => "忘記密碼：臨時密碼信寄送失敗（請檢查 .env 的 MAIL_ 設定）：「{$user->name}」"]);
    }

    public function test_logging_in_with_the_temporary_password_forces_a_new_password_before_anything_else(): void
    {
        Mail::fake();
        $user = $this->user();
        $this->post(route('password.email'), ['email' => 'teacher.a@example.com']);
        $temporary = $this->sentTemporaryPassword();

        // 帳號或 Email 都可以搭配臨時密碼登入。
        $this->post(route('login'), ['login' => 'teacher.a@example.com', 'password' => $temporary])->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertTrue($user->is_password_change_required);
        $this->assertNull($user->temporary_password);   // 只能用一次

        // 設定新密碼之前，其他頁面都被導回設定頁；設定頁與登出可以用。
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('repairs.index'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('你是用臨時密碼登入的');
    }

    public function test_a_temporary_password_cannot_be_used_twice_or_after_it_expires(): void
    {
        Mail::fake();
        $user = $this->user();
        $this->post(route('password.email'), ['email' => 'teacher.a@example.com']);
        $temporary = $this->sentTemporaryPassword();

        $this->post(route('login'), ['login' => 'teacher_a', 'password' => $temporary]);
        auth()->logout();
        $this->post(route('login'), ['login' => 'teacher_a', 'password' => $temporary])->assertSessionHasErrors('login');   // 第二次不行
        $this->assertGuest();

        // 過期的臨時密碼不能登入。
        $user->forceFill(['temporary_password' => 'ExpiredPw12', 'temporary_password_expires_at' => now()->subMinute()])->save();
        $this->post(route('login'), ['login' => 'teacher_a', 'password' => 'ExpiredPw12'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_the_original_password_still_works_after_asking_for_a_temporary_one(): void
    {
        Mail::fake();
        $this->user();
        $this->post(route('password.email'), ['email' => 'teacher.a@example.com']);

        $this->post(route('login'), ['login' => 'teacher_a', 'password' => 'OldPassword1'])->assertRedirect('/dashboard');   // 不會被鎖在門外
    }

    public function test_setting_a_new_password_clears_the_requirement_logs_out_other_devices_and_is_logged(): void
    {
        $user = $this->user(['is_password_change_required' => true]);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($user);

        $this->post(route('password.update'), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');   // 至少 8 碼
        $this->post(route('password.update'), ['password' => 'BrandNewPass9', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->post(route('password.update'), ['password' => 'OldPassword1', 'password_confirmation' => 'OldPassword1'])->assertSessionHasErrors('password');   // 不能和舊的一樣

        $this->post(route('password.update'), ['password' => 'BrandNewPass9', 'password_confirmation' => 'BrandNewPass9'])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->is_password_change_required);
        $this->assertTrue(Hash::check('BrandNewPass9', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_changed', 'loggable_id' => $user->id]);
        $this->get(route('dashboard'))->assertOk();   // 可以正常使用了

        auth()->logout();
        $this->post(route('login'), ['login' => 'teacher_a', 'password' => 'OldPassword1'])->assertSessionHasErrors('login');
        $this->post(route('login'), ['login' => 'teacher_a', 'password' => 'BrandNewPass9'])->assertRedirect('/dashboard');
    }

    public function test_anyone_logged_in_can_open_the_change_page_voluntarily(): void
    {
        $this->actingAs($this->user());

        $this->get(route('password.change'))->assertOk()->assertSee('設定新密碼後，其他裝置上的登入會被登出')->assertSee('回到首頁');
    }

    public function test_the_temporary_password_mail_contains_the_password_and_expiry_in_chinese(): void
    {
        $user = $this->user();
        $mail = new TemporaryPasswordMail($user, 'Ab3kT9xPq2', now()->addMinutes(30));
        $html = $mail->render();

        $this->assertStringContainsString('Ab3kT9xPq2', $html);
        $this->assertStringContainsString('只能用一次', $html);
        $this->assertStringContainsString(now()->addMinutes(30)->format('Y-m-d H:i'), $html);
        $this->assertStringContainsString('如果不是你申請的', $html);
        $this->assertSame('【學校設備維保系統】你的臨時密碼', $mail->envelope()->subject);
    }
}
