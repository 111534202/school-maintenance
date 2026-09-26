<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 維修紀錄。一張報修單（RepairRequest）可以有很多筆維修紀錄——最常見的情況
 * 是驗收不通過退回重修，維修人員就會再填一筆新的紀錄，舊的紀錄不會被刪除，
 * 這樣才能完整看到整個處理過程。
 */
class RepairLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id', // 屬於哪一張報修單
        'cause',             // 故障原因說明
        'resolution',        // 處置方式（怎麼修的）
        'started_at',        // 開始處理時間
        'ended_at',          // 結束處理時間
        'total_hours',       // 總共花了幾小時（由 ended_at - started_at 算出來）
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_hours' => 'decimal:2',
    ];

    /** 這筆維修紀錄屬於哪一張報修單。 */
    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    /** 這筆維修紀錄的附件（維修前後照片），跟 RepairRequest 共用同一張 attachments 表。 */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
