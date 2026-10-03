<?php

// 這個檔案是網站的「總開關」：決定有哪些路由檔、套用哪些中介層、怎麼處理錯誤。一般功能開發不需要改它。

use App\Http\Middleware\CheckRole;                            // 舊的「依角色限制」中介層（目前沒有路由在用，見該檔說明）
use App\Http\Middleware\EnsureUserIsActive;                   // 檢查帳號是否啟用中的中介層
use App\Http\Middleware\SetLocale;                            // 語言切換中介層
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    // 路由設定：網頁路由寫在 routes/web.php、指令寫在 routes/console.php、/up 是健康檢查網址（監控網站有沒有活著）。
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // 中介層設定：中介層是請求進到 Controller 之前會經過的「關卡」。
    ->withMiddleware(function (Middleware $middleware): void {
        // i18n：每個網頁請求都套用使用者目前選的語言（存在 session 裡）。
        // append 代表「加在既有關卡的最後面」。
        $middleware->web(append: [SetLocale::class]);

        // 替中介層取短名字：路由裡寫 'role:admin' 就等於使用 CheckRole（目前沒有路由用到）；
        // 'active' 是「帳號必須啟用中」的檢查，套用在 routes/web.php 登入後的路由群組。
        $middleware->alias([
            'role' => CheckRole::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    // 例外（錯誤）處理設定：目前使用 Laravel 預設行為，所以是空的。
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
