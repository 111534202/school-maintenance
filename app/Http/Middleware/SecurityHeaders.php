<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 替每個網頁回應加上基本的瀏覽器安全標頭（不影響畫面功能）：
 * - X-Frame-Options SAMEORIGIN：別的網站不能把本系統嵌進它的頁面（防點擊劫持）；本系統自己的「在畫面中開啟表單」（同網站的 iframe）不受影響。
 * - X-Content-Type-Options nosniff：瀏覽器不要自己猜檔案類型。
 * - Referrer-Policy：連到別的網站時，只告訴對方「來自哪個網站」，不洩漏完整網址。
 * - Permissions-Policy：只有本站可以用相機（掃描 QR／條碼要用），麥克風與定位一律不開放。
 * - Strict-Transport-Security：只在 https 連線時加，要求瀏覽器之後都用 https。
 * 已經由別處設好的標頭（例如附件下載）不會被覆蓋。
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
