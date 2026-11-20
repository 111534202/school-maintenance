<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 網址上的搜尋與篩選條件被亂改成陣列（例如 ?keyword[]=x、?status[]=y）時，直接當作「沒有帶這個條件」，
 * 避免各個列表頁把陣列當文字處理而整頁 500。
 *
 * 只處理 GET／HEAD 的網址參數；匯出網址本來就會帶勾選清單（ids[]、columns[]、sections[]、cols[]），這幾個名稱放行。
 */
class IgnoreMalformedQuery
{
    /** 網址上合法的「清單」參數。 */
    private const ARRAY_KEYS = ['ids', 'columns', 'sections', 'cols'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            foreach ($request->query->all() as $key => $value) {
                if (is_array($value) && ! in_array($key, self::ARRAY_KEYS, true)) {
                    $request->query->remove($key);
                }
            }
        }

        return $next($request);
    }
}
