<?php

namespace App\Http\Middleware;

use Closure;                                           // 「下一步要做什麼」的函式型別
use Illuminate\Http\Request;                           // 這一次瀏覽器送來的請求
use Illuminate\Support\Facades\Auth;                   // 登入驗證功能
use Symfony\Component\HttpFoundation\Response;         // 回應的型別

/**
 * 每個登入後的請求都檢查：這個帳號是不是「啟用中」。
 *
 * 為什麼需要它？停用帳號時，UserController 會刪掉他的 sessions 把人踢下線，
 * 但如果他當初登入時勾了「記住我」，瀏覽器裡的「記住我」cookie 還在，
 * Laravel 會用那個 cookie 自動把他重新登入——而這條路不會檢查 is_active。
 * 所以這裡在每個請求再檢查一次，發現帳號已被停用就強制登出。
 *
 * 登出時 Laravel 會換掉資料庫裡的 remember_token，舊的「記住我」cookie 從此失效。
 * 註冊在 bootstrap/app.php（別名 active），套用在 routes/web.php 的登入後路由群組。
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();   // 目前登入的人（沒登入是 null，沒登入的情況交給 auth 中介層處理）

        if ($user && ! $user->is_active) {
            Auth::logout();                              // 登出，並換掉 remember_token 讓舊 cookie 失效
            $request->session()->invalidate();           // 讓這組 session 作廢
            $request->session()->regenerateToken();      // 換新的表單防偽 token

            // 導回登入頁並說明原因（和登入失敗時的訊息一致，不另外透露細節）。
            return redirect()->route('login')->withErrors([
                'login' => '帳號已被停用，請聯絡管理員。',
            ]);
        }

        return $next($request);
    }
}
