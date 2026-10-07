<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 操作紀錄（audit_logs 資料表）：誰、在什麼時候、對哪筆資料做了什麼。
 * 一律透過 App\Services\AuditLogger::log() 寫入，不要在別處直接 AuditLog::create。
 * 紀錄只新增、不修改、不刪除；畫面上由 AuditLogController 列出來給有權限的人查詢。
 */
class AuditLog extends Model
{
    // 允許批次寫入的欄位：操作者、事件代碼、對象類型與編號、變更內容、人看得懂的說明。
    protected $fillable = [
        'user_id', 'action', 'loggable_type', 'loggable_id', 'changes', 'description',
    ];

    // changes 欄位在資料庫是 JSON 文字，這裡設成 array：讀出來自動變成 PHP 陣列，存進去自動轉回 JSON。
    protected $casts = [
        'changes' => 'array',
    ];

    /** 這筆紀錄是誰操作的（登入失敗等沒有登入者的紀錄，這裡會是空值）。 */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** 這筆紀錄操作的對象（可能是用戶、設備、報修單...任何資料；多型關聯，靠 loggable_type + loggable_id 找到）。 */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
