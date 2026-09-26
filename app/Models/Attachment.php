<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * 附件（例如故障照片、維修前後照片）。
 *
 * 這張表用「多型關聯」（polymorphic relation）設計，一張表同時給
 * RepairRequest（報修單）和 RepairLog（維修紀錄）共用，不用各自建一張附件表。
 * 判斷「這筆附件是誰的」是靠 attachable_type（存 Model 類別名稱）跟
 * attachable_id（存那個 Model 的 id）這兩個欄位。
 */
class Attachment extends Model
{
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'disk_path',      // 檔案實際存在硬碟上的路徑
        'original_name',  // 使用者上傳時，檔案原本的名稱
        'mime_type',       // 檔案類型，例如 image/jpeg
        'size_bytes',      // 檔案大小（位元組）
    ];

    /** 這筆附件屬於哪一筆資料（可能是 RepairRequest 或 RepairLog）。 */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** 產生一個瀏覽器可以直接打開來看這個檔案的網址。 */
    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }
}
