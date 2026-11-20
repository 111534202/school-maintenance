<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 把網頁回應用 gzip 壓縮後再送出（瀏覽器都會自動解壓縮）。
 *
 * 每個頁面的 HTML 有 130～190 KB（版面裡有大量內嵌的樣式與 script）；Windows 上用 php 內建伺服器（php artisan serve）
 * 開網站時，超過 128 KB 的回應會隨機被截斷、卡約 19 秒才斷線，造成頁面沒樣式、script 被截掉（確認視窗不出現）、
 * 對話框空白。壓縮後只剩 20～30 KB，不再碰到這個問題，正式環境（Apache、nginx）下也能少傳很多資料。
 * 檔案下載、串流回應（匯出 Excel、PDF）不壓縮。
 */
class CompressResponse
{
    private const MIN_BYTES = 2048;

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $response instanceof Response
            || ! function_exists('gzencode')
            || $response->headers->has('Content-Encoding')
            || ! str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type');
        $content = $response->getContent();
        if ($content === false || strlen($content) < self::MIN_BYTES
            || ! preg_match('#^(text/|application/json)#i', $type)) {
            return $response;
        }

        $compressed = gzencode($content, 5);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $response;
    }
}
