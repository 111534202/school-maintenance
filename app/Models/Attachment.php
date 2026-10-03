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

    /** 產生一個瀏覽器可以直接打開來看這個檔案的網址。 */
    public function url(): string
    {
        // 檔案存在 public 磁碟（storage/app/public），url() 把路徑換成可公開存取的網址。
        return Storage::disk('public')->url($this->disk_path);
    }
}
