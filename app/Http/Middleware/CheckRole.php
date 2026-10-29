<?php

namespace App\Http\Middleware;

use Closure;                                           // 「下一步要做什麼」的函式型別
use Illuminate\Http\Request;                           // 這一次瀏覽器送來的請求
use Symfony\Component\HttpFoundation\Response;         // 回應的型別

/**
 * 【中介層（Middleware）是什麼？】請求在進到 Controller 之前會先經過的「關卡」，
 * 可以在這裡檢查條件、不符合就直接擋下來。
 *
 * 這一關的作用：依「角色代碼（slug）」限制誰能進某個路由。
 * 【目前沒有任何路由在用它】路由已經全部改成依「權限」控管（寫法是 can:權限代碼，
 * 權限由身分主檔勾選決定，見 PermissionCatalog），比「寫死某幾個角色」更有彈性。
 * 這個檔案與 bootstrap/app.php 裡的 'role' 別名是舊寫法，保留是為了相容；
 * 確定不會再用的話可以連同別名一起刪掉。
 */
class CheckRole
{
    /**
     * 依角色 slug 限制路由存取
     * 使用方式：Route::middleware('role:admin,it_manager')->group(...)
     *
     * 參數前面的 ... 代表「可以接任意多個角色代碼」，全部收集成 $roles 陣列。
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();   // 目前登入的人（沒登入是 null）

        // 沒登入、沒有身分、或身分代碼不在允許名單裡 → 一律回 403（沒有權限）。
        if (!$user || !$user->role || !in_array($user->role->slug, $roles)) {
            abort(403, '你沒有權限存取此頁面。');
        }

        // 通過檢查，放行：交給下一個關卡（最後就是 Controller）。
        return $next($request);
    }
}
