<?php

namespace App\Support;

/**
 * 伺服器（PHP）允許的上傳大小：單一檔案上限（upload_max_filesize）與整個表單上限（post_max_size）。
 * 畫面上的檔案欄位會在選檔時、送出前先檢查，超過就跳出提示，不會送到伺服器才出現「The POST data is too large」。
 * 這兩個值在 php.ini 設定（Windows 執行 php --ini 可以看到 php.ini 在哪裡）。
 */
class UploadLimit
{
    /** 單一檔案上限（位元組）。 */
    public static function fileBytes(): int
    {
        return self::toBytes((string) ini_get('upload_max_filesize'));
    }

    /** 整個表單（所有檔案加起來）上限（位元組）；0 代表不限制。 */
    public static function postBytes(): int
    {
        return self::toBytes((string) ini_get('post_max_size'));
    }

    /** 把 php.ini 的寫法（例如 8M、2G、512K）換成位元組。 */
    public static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /** 給人看的大小，例如 10 MB。 */
    public static function human(int $bytes): string
    {
        return $bytes >= 1024 ** 2 ? round($bytes / 1024 ** 2, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    }
}
