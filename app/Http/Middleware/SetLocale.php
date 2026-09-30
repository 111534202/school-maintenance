<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * i18n（多語系）語言切換：從 session 讀出使用者上次選的語言，套用到本次請求。
 * 沒選過的話用 config('app.locale') 的預設值（目前是 zh_TW）。
 */
class SetLocale
{
    /** 目前支援的語言代碼，跟 lang/ 資料夾底下的目錄名稱要一致。 */
    private const SUPPORTED_LOCALES = ['zh_TW', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            app()->setLocale($locale);
            // Laravel 不會自動同步 Carbon 的語言，diffForHumans()（例如「等待多久」欄位）
            // 要另外手動設定，不然無論 App 語言切成什麼，都只會顯示英文的 "3 hours ago"。
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
