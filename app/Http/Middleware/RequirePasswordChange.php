<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 用臨時密碼登入之後，在設定新密碼之前，哪裡都不能去：除了「設定新密碼」頁與登出，
 * 其他網址一律導回設定新密碼的頁面。註冊在 bootstrap/app.php（別名 password.fresh），
 * 套用在 routes/web.php 登入後的路由群組。
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_password_change_required && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                abort(403, '請先設定新密碼。');
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
