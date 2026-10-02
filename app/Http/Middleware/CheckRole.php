<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * 依角色 slug 限制路由存取
     * 使用方式：Route::middleware('role:admin,it_manager')->group(...)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->role || !in_array($user->role->slug, $roles)) {
            abort(403, '你沒有權限存取此頁面。');
        }

        return $next($request);
    }
}
