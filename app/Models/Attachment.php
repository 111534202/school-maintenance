<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                       // 所有資料表模型的父類別，提供查詢、儲存等功能
use Illuminate\Database\Eloquent\Relations\MorphTo;           // 「多型關聯」的型別
use Illuminate\Support\Facades\Storage;                       // Laravel 的檔案儲存功能

/**
 * 附件（例如故障照片、維修前後照片）。
 *
 * 【Model 是什麼？】一個 Model 類別對應資料庫的一張資料表（這支對應 attachments），
 * 用 Attachment::create(...)、Attachment::where(...) 就能新增、查詢資料，不用自己寫 SQL。
 *
 * 這張表用「多型關聯」（polymorphic relation）設計，一張表同時給
 * RepairRequest（報修單）和 RepairLog（維修紀錄）共用，不用各自建一張附件表。
 * 判斷「這筆附件是誰的」是靠 attachable_type（存 Model 類別名稱）跟
 * attachable_id（存那個 Model 的 id）這兩個欄位。
 *
 * 檔案本身存在私有磁碟，只能透過 AttachmentController 下載（會檢查報修單的檢視權限）。
 */
class Attachment extends Model
{
    // $fillable：允許用 create([...]) 一次寫入的欄位白名單；沒列在這裡的欄位會被忽略（防止被塞入不該改的欄位）。
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

    /**
     * 開啟這個檔案的網址。檔案存在私有磁碟，不能直接用網址存取，
     * 這個網址會先經過 AttachmentController 檢查「你能不能看這張報修單」才輸出檔案。
     */
    public function url(): string
    {
        return route('attachments.show', $this);
    }

    /**
     * 檔案實際所在的磁碟名稱：新上傳的在私有磁碟（local）；
     * 早期版本上傳、還沒搬走的舊檔案在公開磁碟（public），找不到時回傳 null。
     * （舊檔案會由 2026_10_04 的 migration 搬到私有磁碟，之後這個備援就不會再用到。）
     */
    public function storageDisk(): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($this->disk_path)) {
                return $disk;
            }
        }

        return null;
    }
}
