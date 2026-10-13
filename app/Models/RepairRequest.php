<?php

namespace App\Models;

use App\Enums\RepairRequestStatus;   // 報修單狀態列舉
use App\Policies\RepairRequestPolicy;   // 報修單的歸屬權限（誰看得到、誰能處理）
use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;   // 「屬於某一筆」關聯的型別
use Illuminate\Database\Eloquent\Relations\MorphMany;   // 「一對多的多型關聯」的型別

/**
 * 「報修單」Model。使用者發現設備壞掉時，填一張報修單，之後主管會指派維修人員、
 * 維修人員處理完會填一筆或多筆維修紀錄（見 RepairLog），最後報修人驗收通過就結案。
 *
 * 重要提醒：
 * - devices/users 表已經合併進來了（林政寬的 feature/auth-device），reporter_id
 *   （報修人）、device_id（設備）、assigned_to（維修人員）現在都有正式外鍵約束，
 *   也有下面對應的 belongsTo() 關聯可以直接用。device_note / assignee_note 兩個
 *   文字欄位保留當後備顯示（例如保養 NG 自動轉入、沒有對應真實設備/帳號的案件）。
 * - status（案件狀態）欄位請一律透過 App\Services\RepairRequestWorkflow 這支
 *   service 來修改，不要在 Controller 或其他地方直接寫 `$repairRequest->status = ...`。
 *   因為狀態怎麼轉換是有規則的（例如「新報修」不能直接跳到「處理中」），這些
 *   規則全部寫在那支 service 裡，統一管理比較不會出錯。
 */
class RepairRequest extends Model
{
    use HasFactory;   // 可以用 RepairRequest::factory() 產生測試資料

    // 資料表名稱（明確寫出來，避免 Laravel 自己猜錯）。
    protected $table = 'repair_requests';

    /**
     * 可以透過「批次寫入」（例如 create($array)）設定的欄位。
     * 欄位說明：
     * - title / description：報修標題、故障描述（使用者填的）
     * - impact_level：影響程度，值只能是 low（輕微）/medium（中等）/high（嚴重）
     * - affects_class：是否正在影響上課（布林值）
     * - status：案件狀態，見上面的「重要提醒」，一般不要直接指派這個欄位
     * - reporter_id / device_id / assigned_to：報修人／設備／維修人員的 id
     *   （對應 users、devices、users 資料表，已有外鍵約束）
     * - device_note：沒有掃描設備條碼、手動輸入的案件，用文字描述設備位置（device_id 會是空的）
     * - assignee_note：沒有真實維修人員帳號（例如保養 NG 轉入的舊資料）時，用文字記錄維修人員姓名
     * - scheduled_at：預計處理日期
     * - rejection_reason：驗收不通過退回時，驗收人填的退回原因
     * - location：地點（教室），文字欄位，看板可依它模糊篩選
     */
    protected $fillable = [
        'title',
        'description',
        'impact_level',
        'affects_class',
        'status',
        'reporter_id',
        'device_id',
        'device_note',
        'assigned_to',
        'assignee_note',
        'scheduled_at',
        'rejection_reason',
        'location',
    ];

    /**
     * 型別轉換：
     * - affects_class：資料庫存的是 0/1，這裡自動轉成 PHP 的 true/false
     * - status：資料庫存的是文字字串（例如 "pending"），這裡自動轉成
     *   App\Enums\RepairRequestStatus 這個列舉物件，用起來比較不會打錯字
     * - scheduled_at：資料庫存的是時間字串，這裡自動轉成 Carbon 日期物件，
     *   才能用 ->format('Y-m-d H:i') 這種方法
     */
    protected $casts = [
        'affects_class' => 'boolean',
        'status' => RepairRequestStatus::class,
        'scheduled_at' => 'datetime',
    ];

    /**
     * 這張報修單底下的所有維修紀錄（一張報修單可以有多筆維修紀錄，
     * 例如驗收不通過退回重修，就會多一筆新的維修紀錄）。
     */
    public function repairLogs()
    {
        return $this->hasMany(RepairLog::class);
    }

    /**
     * 這張報修單附的檔案（例如故障照片）。attachments 表是共用表，
     * 同一張表也被 RepairLog（維修前後照片）使用，用「多型關聯」區分是誰的附件。
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * 只撈「這位用戶看得到」的報修單（規則見 App\Policies\RepairRequestPolicy）：
     * 能看全部的人（管理員、有派工權限）不加條件；其他人只看自己報修的或指派給自己的。
     * 使用時寫 RepairRequest::visibleTo($user)->...；看板、主控台統計、通知鈴鐺都用它，數字才會一致。
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (RepairRequestPolicy::canSeeAll($user)) {
            return $query;
        }

        // 括號包起來：「自己報修」或「指派給自己」二選一，不會影響外面其他篩選條件。
        return $query->where(function (Builder $q) use ($user) {
            $q->where('reporter_id', $user->id)->orWhere('assigned_to', $user->id);
        });
    }

    /** 這張報修單是報修哪一台真實設備（掃描設備條碼建立的報修單才會有值）。 */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** 送出這張報修單的使用者。 */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** 目前被指派處理這張報修單的維修人員。 */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
