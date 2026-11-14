<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * 忘記密碼與設定新密碼。
 *
 * 流程：
 * 1. 在登入頁按「忘記密碼」，輸入註冊帳號時填的 Email（GET/POST /forgot-password）。
 * 2. 系統寄「臨時密碼」到那個 Email（30 分鐘內有效，只能用一次）。畫面上不論 Email 對不對，都顯示同一句話，
 *    不讓外人用這個功能探測哪些 Email 有註冊。
 * 3. 用帳號（或 Email）＋臨時密碼登入（LoginController），登入後只能停在「設定新密碼」頁（RequirePasswordChange）。
 * 4. 設定新密碼（GET/POST /password/change）成功後，其他裝置上的登入全部登出，才能正常使用系統。
 *
 * 臨時密碼和原本的密碼分開存（users.temporary_password），所以有人亂按「忘記密碼」不會蓋掉本人原本的密碼。
 * 同一個 Email 一小時最多寄 3 次；同一個 IP 一分鐘最多送 5 次（路由的 throttle）。
 * 寄信用 .env 的 MAIL_*（目前 MAIL_MAILER=log 只會寫進 storage/logs，上線前要換成真的 SMTP）。
 */
class PasswordRecoveryController extends Controller
{
    /** 臨時密碼的有效時間（分鐘）。 */
    public const TEMPORARY_PASSWORD_MINUTES = 30;

    /** 同一個 Email 一小時內最多寄幾次。 */
    private const MAX_REQUESTS_PER_EMAIL = 3;

    /** 臨時密碼用的字元：拿掉容易看錯的（0 O 1 l I），方便照著信件打。 */
    private const PASSWORD_CHARS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** 忘記密碼表單（GET /forgot-password）。 */
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /** 寄出臨時密碼（POST /forgot-password）。不論 Email 有沒有註冊，回應都一樣。 */
    public function sendTemporaryPassword(Request $request)
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:255']], [
            'email.required' => '請輸入註冊帳號時填的 Email。',
            'email.email' => 'Email 格式不正確。',
        ]);
        $email = Str::lower(trim($input['email']));

        $generic = redirect()->route('login')->with('status', '如果這個 Email 對應到一個啟用中的帳號，臨時密碼已經寄出（' . self::TEMPORARY_PASSWORD_MINUTES . ' 分鐘內有效）。'
            . '請到信箱查看，用臨時密碼登入後再設定新密碼。沒收到的話請檢查垃圾郵件匣，或聯絡資訊組。');

        $rateKey = 'forgot-password|' . $email;
        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_REQUESTS_PER_EMAIL)) {
            return $generic;   // 一小時內寄太多次：安靜地不再寄（回應跟平常一樣）
        }
        RateLimiter::hit($rateKey, 3600);

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->where('is_active', true)->first();
        if (! $user) {
            return $generic;
        }

        $temporary = $this->makeTemporaryPassword();
        $expiresAt = now()->addMinutes(self::TEMPORARY_PASSWORD_MINUTES);
        // temporary_password 有 hashed 轉換，存進去會自動雜湊（資料庫裡看不到明碼）。
        $user->forceFill(['temporary_password' => $temporary, 'temporary_password_expires_at' => $expiresAt])->save();

        try {
            Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporary, $expiresAt));
            AuditLogger::log('password_reset', $user, [], "忘記密碼：已寄出臨時密碼給「{$user->name}」的註冊 Email");
        } catch (\Throwable $e) {
            // 寄信失敗（SMTP 設定錯誤、網路不通…）：把臨時密碼作廢，記下原因讓管理員查；畫面維持同樣的回應。
            $user->forceFill(['temporary_password' => null, 'temporary_password_expires_at' => null])->save();
            Log::error('臨時密碼信寄送失敗：' . $e->getMessage());
            AuditLogger::log('password_reset', $user, [], "忘記密碼：臨時密碼信寄送失敗（請檢查 .env 的 MAIL_ 設定）：「{$user->name}」");
        }

        return $generic;
    }

    /** 設定新密碼的表單（GET /password/change）：用臨時密碼登入後會被帶到這裡；平常登入的人也可以自己來改密碼。 */
    public function showChangeForm(Request $request)
    {
        return view('auth.change-password', ['forced' => (bool) $request->user()->is_password_change_required]);
    }

    /** 儲存新密碼（POST /password/change）。 */
    public function change(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', Password::min(8), 'confirmed', function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                if (Hash::check((string) $value, $user->password)) {
                    $fail('新密碼不能和目前的密碼相同。');
                }
            }],
        ], [
            'password.required' => '請輸入新密碼。',
            'password.confirmed' => '兩次輸入的新密碼不一樣。',
        ]);

        $user->forceFill([
            'password' => $validated['password'],   // password 有 hashed 轉換，會自動雜湊
            'temporary_password' => null,
            'temporary_password_expires_at' => null,
            'is_password_change_required' => false,
        ])->save();

        // 其他裝置上這個帳號的登入全部登出（密碼換了，舊的登入不該繼續有效）；目前這個登入保留。
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        AuditLogger::log('password_changed', $user, [], "「{$user->name}」設定了新密碼");

        return redirect()->route('dashboard')->with('success', '新密碼已設定，下次請用新密碼登入。');
    }

    /** 產生 10 碼的臨時密碼（密碼學安全的亂數）。 */
    private function makeTemporaryPassword(): string
    {
        $chars = self::PASSWORD_CHARS;
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
