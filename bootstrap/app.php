<?php

// 這個檔案是網站的「總開關」：決定有哪些路由檔、套用哪些中介層、怎麼處理錯誤。一般功能開發不需要改它。

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\FlagDiscardedUploads;                 // 有附件的表單驗證失敗時，提醒附件要重新選
use App\Http\Middleware\CompressResponse;                    // 網頁回應 gzip 壓縮（避免大頁面在 php 內建伺服器上被截斷）
use App\Http\Middleware\EmbeddedModal;                        // 「在畫面中」開啟的表單送出成功後，通知外面的頁面                            // 舊的「依角色限制」中介層（目前沒有路由在用，見該檔說明）
use App\Http\Middleware\EnsureUserIsActive;                   // 檢查帳號是否啟用中的中介層
use App\Http\Middleware\IgnoreMalformedQuery;                  // 網址篩選條件被亂改成陣列時忽略它（避免 500）
use App\Http\Middleware\SecurityHeaders;                    // 基本瀏覽器安全標頭
use App\Http\Middleware\RequirePasswordChange;                 // 用臨時密碼登入後，設定新密碼前哪裡都不能去
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
    // 自動載入 app/Console/Commands 目錄中的自訂指令。
    ->withCommands()
    // 中介層設定：中介層是請求進到 Controller 之前會經過的「關卡」。
    ->withMiddleware(function (Middleware $middleware): void {
        // i18n：每個網頁請求都套用使用者目前選的語言（存在 session 裡）。
        // append 代表「加在既有關卡的最後面」。
        $middleware->web(append: [SetLocale::class, FlagDiscardedUploads::class, EmbeddedModal::class, SecurityHeaders::class, IgnoreMalformedQuery::class, CompressResponse::class]);

        // 替中介層取短名字：路由裡寫 'role:admin' 就等於使用 CheckRole（目前沒有路由用到）；
        // 'active' 是「帳號必須啟用中」的檢查，套用在 routes/web.php 登入後的路由群組。
        $middleware->alias([
            'role' => CheckRole::class,
            'active' => EnsureUserIsActive::class,
            'password.fresh' => RequirePasswordChange::class,   // 用臨時密碼登入後，設定新密碼前哪裡都不能去
        ]);
    })
    // 例外（錯誤）處理設定：目前使用 Laravel 預設行為，所以是空的。
    ->withExceptions(function (Exceptions $exceptions): void {
        // 網址上的篩選條件格式不對（例如被手改成不存在的日期）：回到「沒有篩選條件的同一頁」並提示。
        // 不用「回上一頁」：重新整理時上一頁就是這個網址本身，會變成無限轉址（ERR_TOO_MANY_REDIRECTS）。
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->isMethod('GET') && ! $request->expectsJson() && $e->redirectTo === null) {
                return redirect($request->url())->withErrors($e->errors());
            }

            return null;
        });
    })->create();
