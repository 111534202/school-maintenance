<?php

namespace App\Models;

use App\Enums\RepairRequestStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;   // 軟刪除：刪除只是標記時間，資料還在

/**
 * 設備（devices 資料表）：每台設備有類別、所在教室與狀態。
 * 設備的「狀態」與「核心設備」旗標請透過 App\Services\DeviceStatusService 修改，
 * 才會自動寫操作紀錄並同步教室的異常標記。
 *
 *
 * @property int $id
 * @property string $device_code
 * @property string|null $asset_code
 * @property int $device_category_id
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property \Illuminate\Support\Carbon|null $warranty_end_date
 * @property int $classroom_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $import_batch_id
 */
class Device extends Model
{
    use SoftDeletes;   // 啟用軟刪除（停用設備時用 delete()，資料保留）

    // 允許批次寫入的欄位白名單。
    protected $fillable = [
        'device_code', 'asset_code', 'device_category_id', 'brand',
        'model', 'serial_number', 'warranty_end_date', 'classroom_id',
        'status',
    ];

    // 型別轉換：warranty_end_date 轉成日期物件，才能用 ->format('Y-m-d')。（「核心設備」欄位已拿掉。）
    protected $casts = [
        'warranty_end_date' => 'date',
    ];

    // 存在資料庫的設備狀態（人工設定）：normal=正常, retired=已淘汰, disabled=停用。
    // 「待維修」不存：設備有「已派工」或「待驗收」的報修單時，畫面上顯示待維修（依《報修與保養流程修改說明》，
    // 原本由報修單「處理中」自動改成「維修中」的做法已拿掉）。顯示用的代碼是 AWAITING_REPAIR。
    // 新增狀態時：這裡加代碼 → lang/各語言資料夾/devices.php 加顯示名稱 → lang/各語言資料夾/dashboard.php 的 device_status 也加。
    public const STATUSES = ['normal', 'retired', 'disabled'];

    // 畫面上的「待維修」（由報修單推算，不存在資料庫）。
    public const AWAITING_REPAIR = 'repairing';

    // 畫面上會出現的狀態（篩選、圖表、徽章）：正常、待維修、已淘汰、停用。
    public const DISPLAY_STATUSES = ['normal', self::AWAITING_REPAIR, 'retired', 'disabled'];

    // 「重複報修」的判斷基準：同一台設備在這段天數內被報修達到這個次數（含現在這一張），
    // 列表／看板就會特別標示提醒，這種設備通常比單次故障更值得優先檢查或考慮汰換。
    public const REPEAT_REPAIR_DAYS = 30;
    public const REPEAT_REPAIR_THRESHOLD = 2;

    /** 狀態的顯示名稱（中文／英文依目前語言），翻譯檔沒有的代碼就原樣顯示。 */
    public static function statusLabel(?string $status): string
    {
        $key = 'devices.status.' . $status;   // 翻譯鍵，例如 devices.status.normal
        $label = __($key);                    // __() 查翻譯；找不到時會原樣回傳鍵名

        // 回傳的還是鍵名代表沒有翻譯，這時改顯示原本的狀態代碼，不要讓畫面出現一串鍵名。
        return $label === $key ? (string) $status : $label;
    }

    /** 狀態對應的徽章顏色（Bootstrap）：正常綠、待維修黃、已淘汰灰、停用紅，和主控台的設備狀態圖同一套顏色。 */
    public static function statusBadgeClass(?string $status): string
    {
        return [
            'normal' => 'text-bg-success',
            'repairing' => 'text-bg-warning',
            'retired' => 'text-bg-secondary',
            'disabled' => 'text-bg-danger',
        ][$status] ?? 'text-bg-light';
    }

    /**
     * 畫面上要顯示的狀態：正常的設備如果有「已派工／待驗收」的報修單，就顯示「待維修」（AWAITING_REPAIR）；
     * 其他照資料庫的狀態。列表請先用 withAwaitingRepair() 一次查好，避免每台設備各查一次。
     */
    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'normal' && $this->isAwaitingRepair() ? self::AWAITING_REPAIR : (string) $this->status;
    }

    /** 這台設備是不是「待維修」：有已派工或待驗收的報修單（新報修還沒派工不算）。 */
    public function isAwaitingRepair(): bool
    {
        if (array_key_exists('awaiting_repair', $this->attributes)) {
            return (bool) $this->attributes['awaiting_repair'];
        }

        return $this->repairRequests()->whereIn('status', RepairRequestStatus::awaitingRepairValues())->exists();
    }

    /** 查詢時順便算好「是不是待維修」（awaiting_repair），列表顯示 display_status 才不用每台再查。 */
    public function scopeWithAwaitingRepair(Builder $query): Builder
    {
        return $query->withExists(['repairRequests as awaiting_repair' => fn ($repairs) => $repairs->whereIn('status', RepairRequestStatus::awaitingRepairValues())]);
    }

    /** 查詢範圍：只撈「待維修」的設備（有已派工或待驗收的報修單）。 */
    public function scopeAwaitingRepair(Builder $query): Builder
    {
        return $query->whereHas('repairRequests', fn ($repairs) => $repairs->whereIn('status', RepairRequestStatus::awaitingRepairValues()));
    }

    /**
     * 查詢範圍：照畫面上的狀態篩選（設備主檔的狀態下拉選單、主控台圖表連過來）：
     * 待維修＝有已派工／待驗收報修單的設備；正常＝狀態正常而且不是待維修；其他照資料庫的狀態。
     */
    public function scopeWhereDisplayStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            self::AWAITING_REPAIR => $query->where('status', 'normal')->awaitingRepair(),
            'normal' => $query->where('status', 'normal')->whereDoesntHave('repairRequests', fn ($repairs) => $repairs->whereIn('status', RepairRequestStatus::awaitingRepairValues())),
            default => $query->where('status', $status),
        };
    }

    /**
     * 這台設備是不是「異常設備」：有未結束的報修單（已結案、已退回不算），已淘汰的設備不算。
     * 教室主檔的「異常設備」數字用這個定義（依《報修與保養流程修改說明》，原本是看設備狀態）。
     */
    public function isAbnormal(): bool
    {
        return $this->status !== 'retired' && $this->repairRequests()->open()->exists();
    }

    /** 查詢範圍：只撈異常設備（定義同 isAbnormal；停用的設備已軟刪除，本來就不會被撈到）。使用時寫 Device::abnormal()->...。 */
    public function scopeAbnormal(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '<>', 'retired')
            ->whereHas('repairRequests', fn ($repairs) => $repairs->open());
    }

    /**
     * 這台設備的所有報修單（報修時有掃描或選擇設備的才會有 device_id）。
     *
     * @return HasMany<RepairRequest, $this>
     */
    public function repairRequests(): HasMany
    {
        return $this->hasMany(RepairRequest::class);
    }

    /**
     * 這台設備的類別；外鍵欄位不是預設的 category_id，所以要特別指定 device_category_id。
     *
     * @return BelongsTo<DeviceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }

    /**
     * 這台設備所在的教室。
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
