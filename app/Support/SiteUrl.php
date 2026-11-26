<?php

namespace App\Support;

/**
 * 寄信用的網址：一律以 .env 的 APP_URL 組合，不用 url()／route() 的完整網址。
 * 完整網址會取自這次請求的 Host 標頭，別人偽造 Host 就能讓系統寄出「按鈕指向他網站」的忘記密碼信。
 */
class SiteUrl
{
    public static function to(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
