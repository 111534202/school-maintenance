<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * 附件上限（全站共用：報修單、維修紀錄、零件採購）：
 * 一筆最多 5 個檔案，而且全部加起來不能超過 5 MB；單一檔案不另外限制大小（只要總和不超過）。
 * 要調整就改這兩個數字，畫面上的提示與檢查會一起變。
 */
class AttachmentLimit
{
    public const MAX_FILES = 5;

    public const MAX_TOTAL_KB = 5120;   // 5 MB

    /** 後端接受的附件副檔名（報修、維修紀錄、保養結果共用）。 */
    public const MIMES = 'jpg,jpeg,png,webp,pdf,mp4,mov,webm';

    /**
     * 檔案欄位的 accept：用「檔案類型」而不是副檔名。手機瀏覽器看到 image/jpeg 這類類型，才會直接給「拍照／相簿」；
     * 只寫副檔名時，Android 會跳到一般的檔案管理員、找不到相簿裡的照片；iPhone 看到只收 jpeg／png，會自動把 HEIC 照片轉成 JPEG。
     */
    public const ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/quicktime,video/webm';

    public static function maxTotalBytes(): int
    {
        return self::MAX_TOTAL_KB * 1024;
    }

    /** 給人看的說明，例如「最多 5 個檔案，加起來 5 MB 以內」。 */
    public static function hint(): string
    {
        return '最多 ' . self::MAX_FILES . ' 個檔案，加起來 ' . (self::MAX_TOTAL_KB / 1024) . ' MB 以內';
    }

    /**
     * 驗證規則：這次上傳的檔案（加上 $existingBytes＝這一筆原本保留的附件大小）總和不能超過上限。
     * 用法：'attachments' => ['nullable', 'array', 'max:5', AttachmentLimit::totalSizeRule()]
     */
    public static function totalSizeRule(int $existingBytes = 0): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($existingBytes) {
            $files = array_filter(is_array($value) ? $value : [$value], fn ($f) => $f instanceof UploadedFile);
            $total = $existingBytes + array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files));
            if ($total > self::maxTotalBytes()) {
                $fail('附件加起來最多 ' . (self::MAX_TOTAL_KB / 1024) . ' MB，目前是 ' . UploadLimit::human($total) . '，請移除一些檔案或改選較小的檔案。');
            }
        };
    }
}
