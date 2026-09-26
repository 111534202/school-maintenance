<?php

namespace App\Models;

use App\Enums\RepairRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 「報修單」Model。使用者發現設備壞掉時，填一張報修單，之後主管會指派維修人員、
 * 維修人員處理完會填一筆或多筆維修紀錄（見 RepairLog），最後報修人驗收通過就結案。
 *
 * 重要提醒：
 * - users/devices 表現在還沒合併進這個分支，所以 reporter_id（報修人）、
 *   device_id（設備）、assigned_to（維修人員）目前只是單純存一個數字 id，
 *   沒有真正的資料庫外鍵關聯，也沒有 Eloquent 的 belongsTo() 關聯方法。
 *   等其他組員的表合併進來後，才會補上正式的關聯。
 * - status（案件狀態）欄位請一律透過 App\Services\RepairRequestWorkflow 這支
 *   service 來修改，不要在 Controller 或其他地方直接寫 `$repairRequest->status = ...`。
 *   因為狀態怎麼轉換是有規則的（例如「新報修」不能直接跳到「處理中」），這些
 *   規則全部寫在那支 service 裡，統一管理比較不會出錯。
 */
class RepairRequest extends Model
{
    use HasFactory;

    protected $table = 'repair_requests';

    /**
     * 可以透過「批次寫入」（例如 create($array)）設定的欄位。
     * 欄位說明：
     * - title / description：報修標題、故障描述（使用者填的）
     * - impact_level：影響程度，值只能是 low（輕微）/medium（中等）/high（嚴重）
     * - affects_class：是否正在影響上課（布林值）
     * - status：案件狀態，見上面的「重要提醒」，一般不要直接指派這個欄位
     * - reporter_id / device_id / assigned_to：報修人／設備／維修人員的 id
     *   （目前只是數字，還沒有真正關聯到其他表，見上面說明）
     * - device_note：devices 表合併前，暫時讓使用者自己打字描述設備位置
     * - assignee_note：users 表合併前，暫時讓主管自己打字記錄維修人員姓名
     * - scheduled_at：預計處理日期
     * - rejection_reason：驗收不通過退回時，驗收人填的退回原因
     * - location：地點（教室），跟 device_note 一樣是暫時的文字欄位
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
}
