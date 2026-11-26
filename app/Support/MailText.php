<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * 把使用者打的文字放進 Markdown 信件前先跳脫。
 *
 * Blade 的 {{ }} 只跳脫 HTML，信件版面之後還會做一次 Markdown 解析：報修標題寫成
 * [點我登入](http://…) 或 ![](http://…) 就會變成系統寄出的真連結與外部圖片。
 * 這裡把 Markdown 用到的符號改成 HTML 字元編號，畫面看起來一樣、但不再有語法作用
 * （純文字版的信會把編號還原成原字元）。
 */
class MailText
{
    private const MARKDOWN_CHARS = ['\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '#', '+', '-', '.', '!', '|', '~', '=', ':'];

    /** 單行欄位（標題、地點、姓名）：換行改成空白，避免跳出表格或段落。 */
    public static function line(?string $value): HtmlString
    {
        return new HtmlString(self::encode((string) preg_replace('/\s*[\r\n]+\s*/u', ' ', (string) $value)));
    }

    /** 多行內容（故障描述）：保留換行，行首空白拿掉以免變成程式碼區塊。 */
    public static function block(?string $value): HtmlString
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];

        return new HtmlString(implode("\n", array_map(fn (string $line): string => self::encode(ltrim($line)), $lines)));
    }

    private static function encode(string $value): string
    {
        $map = ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;', "'" => '&#039;'];

        foreach (self::MARKDOWN_CHARS as $char) {
            $map[$char] = '&#' . ord($char) . ';';
        }

        // strtr 一次掃完，剛換上的編號不會再被替換一次。
        return strtr($value, $map);
    }
}
