<?php

namespace App\Http\Middleware;

use Carbon\Carbon;                                     // 日期時間工具（也負責「3 小時前」這種文字的語言）
use Closure;                                           // 「下一步要做什麼」的函式型別
use Illuminate\Http\Request;                           // 這一次瀏覽器送來的請求
use Symfony\Component\HttpFoundation\Response;         // 回應的型別

/**
 * i18n（多語系）語言切換：從 session 讀出使用者上次選的語言，套用到本次請求。
 * 沒選過的話用 config('app.locale') 的預設值（目前是 zh_TW）。
 *
 * 這是「中介層」：每個網頁請求都會先經過它（註冊在 bootstrap/app.php）。
 * 目前畫面上的語言切換選單預設是隱藏的（config('app.locale_switcher')），所以實際上都是預設語言。
 */
class SetLocale
{
    /** 目前支援的語言代碼，跟 lang/ 資料夾底下的目錄名稱要一致。新增語言時要在這裡加。 */
    private const SUPPORTED_LOCALES = ['zh_TW', 'en'];

    // 中介層的主要方法：每個網頁請求進來都會先執行一次，做完設定再交給下一個關卡。
    public function handle(Request $request, Closure $next): Response
    {
        // 從 session 取出使用者選的語言；沒選過就用設定檔的預設語言。
        $locale = $request->session()->get('locale', config('app.locale'));

        // 只接受支援的語言，其他任意字串一律忽略，避免被亂塞值。
        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            app()->setLocale($locale);   // 讓整個程式之後用 __() 查翻譯時改用這個語言
            // Laravel 不會自動同步 Carbon 的語言，diffForHumans()（例如「等待多久」欄位）
            // 要另外手動設定，不然無論 App 語言切成什麼，都只會顯示英文的 "3 hours ago"。
            Carbon::setLocale($locale);
        }

        // 放行，交給下一個關卡（最後是 Controller）。
        return $next($request);
    }
}
