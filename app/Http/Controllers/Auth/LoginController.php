<?php

// 命名空間：這個類別所在的位置（Auth 子資料夾），要跟資料夾路徑對得上。
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;       // 所有 Controller 的共同父類別（在上一層資料夾，所以要特別 use）
use App\Services\AuditLogger;              // 共用的操作紀錄寫入工具
use Illuminate\Http\Request;               // 這一次瀏覽器送來的請求
use Illuminate\Support\Facades\Auth;       // Laravel 內建的「登入驗證」功能
use Illuminate\Support\Facades\RateLimiter; // 次數限制：記錄一段時間內失敗了幾次
use Illuminate\Support\Str;                // 字串小工具（這裡用來把帳號統一轉小寫）

/**
 * 登入與登出。
 * - 登入欄位同時接受「帳號名稱」或「Email」。
 * - 被停用的帳號不能登入；成功與失敗都會寫進操作紀錄（失敗只記帳號與 IP，不記密碼）。
 * - 成功登入會更新「最後登入時間」，用戶主檔上看得到。
 * - 登入失敗次數過多會暫時鎖定（見下方 MAX_ATTEMPTS 等常數），防止被無限次猜密碼。
 * 這三個網址不需要登入就能進（見 routes/web.php），其餘頁面都要先登入。
 */
class LoginController extends Controller
{
    // 登入失敗次數限制（防止被無限次猜密碼）：
    // - 同一個「帳號 + IP」在 DECAY_SECONDS 秒內最多失敗 MAX_ATTEMPTS 次，超過就暫時鎖定；
    // - 同一個 IP 不管換多少帳號，在同一段時間內最多失敗 MAX_ATTEMPTS_PER_IP 次。
    // 想放寬或加嚴，改這三個數字就好。登入成功會清掉該「帳號 + IP」的失敗次數。
    private const MAX_ATTEMPTS = 5;
    private const MAX_ATTEMPTS_PER_IP = 20;
    private const DECAY_SECONDS = 300;

    /** 顯示登入表單（GET /login）。 */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /** 接收登入表單（POST /login）。 */
    public function login(Request $request)
    {
        // 帳號欄位與密碼欄位都必須有填，沒填會導回表單並顯示錯誤。
        $input = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // 次數限制的計數鍵：帳號統一轉小寫（大小寫不同不能用來繞過），再加上來源 IP。
        $throttleKey = Str::lower($input['login']) . '|' . $request->ip();
        $ipKey = 'login-ip|' . $request->ip();

        // 失敗次數已經超過上限：直接拒絕，連密碼對不對都不檢查（鎖定期間輸入正確密碼也一樣不能登入）。
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            // availableIn：還要等幾秒才會解除；兩個限制取比較久的那個。
            $seconds = max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($ipKey));

            return back()->withErrors([
                'login' => "登入失敗次數過多，請在 {$seconds} 秒後再試。",
            ])->onlyInput('login');
        }

        // 同一個欄位同時支援「帳號名稱」與「Email」：長得像 Email 就用 email 欄位比對，
        // 否則用 username 欄位比對。
        $field = filter_var($input['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        // is_active => true：用戶主檔裡被停用的帳號不能登入（被刪除的帳號本來就查不到）。
        // Laravel 會自動把輸入的密碼加密後與資料庫比對，所以這裡直接放使用者輸入的密碼即可。
        $credentials = [$field => $input['login'], 'password' => $input['password'], 'is_active' => true];

        // attempt：嘗試登入，帳密正確回傳 true。第二個參數是「記住我」勾選與否。
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // 登入成功後換一組新的 session 編號，防止「session 固定攻擊」（別人預先塞給你的舊編號）。
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);   // 登入成功：清掉這個「帳號 + IP」累積的失敗次數
            // forceFill：略過 $fillable 保護直接寫入欄位，因為最後登入時間是系統寫的，不是使用者填的。
            Auth::user()->forceFill(['last_login_at' => now()])->save();
            AuditLogger::log('login', Auth::user(), [], __('audit.messages.login', ['name' => Auth::user()->name]));
            // intended：如果使用者原本想去某個頁面但被擋下來要求登入，登入後就送他回去那一頁；沒有就去主控台。
            return redirect()->intended('/dashboard');
        }

        // 登入失敗：兩個限制各計一次（DECAY_SECONDS 秒後自動歸零）。
        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);

        // 登入失敗也要留紀錄（稽核常看的項目）：只記輸入的帳號，絕對不記密碼。
        AuditLogger::log('login_failed', null, ['ip' => $request->ip()], __('audit.messages.login_failed', ['account' => $input['login']]));

        // 不特別說明是「密碼錯」還是「帳號被停用」，避免讓外人探測帳號是否存在。
        // onlyInput('login')：導回表單時只保留帳號欄位，密碼欄位一定清空。
        return back()->withErrors([
            'login' => '帳號或密碼錯誤，或帳號已被停用。',
        ])->onlyInput('login');
    }

    /** 登出（POST /logout）。 */
    public function logout(Request $request)
    {
        // 先寫紀錄再登出：登出之後就不知道是誰了。?-> 是「如果沒有登入就不要報錯」。
        AuditLogger::log('logout', Auth::user(), [], __('audit.messages.logout', ['name' => Auth::user()?->name]));
        Auth::logout();                              // 登出
        $request->session()->invalidate();           // 讓這組 session 作廢
        $request->session()->regenerateToken();      // 換新的表單防偽 token（CSRF），舊頁面的表單從此失效
        return redirect('/login');
    }
}
