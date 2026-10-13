<?php

namespace App\Models;

use App\Services\DeviceStatusService;   // 提供「哪些狀態算異常」的唯一定義
use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;   // 軟刪除：刪除只是標記時間，資料還在

/**
 * 設備（devices 資料表）：每台設備有類別、所在教室與狀態。
 * 設備的「狀態」與「核心設備」旗標請透過 App\Services\DeviceStatusService 修改，
 * 才會自動寫操作紀錄並同步教室的異常標記。
 */
class Device extends Model
{
    use SoftDeletes;   // 啟用軟刪除（停用設備時用 delete()，資料保留）

    // 允許批次寫入的欄位白名單。
    protected $fillable = [
        'device_code', 'asset_code', 'device_category_id', 'brand',
        'model', 'serial_number', 'warranty_until', 'classroom_id',
        'status', 'is_core',
    ];

    // 型別轉換：is_core 資料庫存 0/1 → PHP 的 true/false；warranty_until 轉成日期物件，才能用 ->format('Y-m-d')。
    protected $casts = [
        'is_core' => 'boolean',
        'warranty_until' => 'date',
    ];

    // 設備狀態列舉：normal=正常, repairing=維修中, retired=已淘汰, disabled=停用
    // 新增狀態時：這裡加代碼 → lang/各語言資料夾/devices.php 加顯示名稱 → lang/各語言資料夾/dashboard.php 的 device_status 也加。
    public const STATUSES = ['normal', 'repairing', 'retired', 'disabled'];

    /** 狀態的顯示名稱（中文／英文依目前語言），翻譯檔沒有的代碼就原樣顯示。 */
    public static function statusLabel(?string $status): string
    {
        $key = 'devices.status.' . $status;   // 翻譯鍵，例如 devices.status.normal
        $label = __($key);                    // __() 查翻譯；找不到時會原樣回傳鍵名

        // 回傳的還是鍵名代表沒有翻譯，這時改顯示原本的狀態代碼，不要讓畫面出現一串鍵名。
        return $label === $key ? (string) $status : $label;
    }

    /** 狀態對應的徽章顏色（Bootstrap）：正常綠、維修中黃、已淘汰灰、停用紅，和主控台的設備狀態圖同一套顏色。 */
    public static function statusBadgeClass(?string $status): string
    {
        return [
            'normal' => 'text-bg-success',
            'repairing' => 'text-bg-warning',
            'retired' => 'text-bg-secondary',
            'disabled' => 'text-bg-danger',
        ][$status] ?? 'text-bg-light';
    }

    /** 這台設備是不是「異常設備」（狀態在 DeviceStatusService::PROBLEM_STATUSES 清單裡）。 */
    public function isAbnormal(): bool
    {
        return in_array($this->status, DeviceStatusService::PROBLEM_STATUSES, true);
    }

    /** 查詢範圍：只撈異常設備。使用時寫 Device::abnormal()->...。 */
    public function scopeAbnormal(Builder $query): Builder
    {
        return $query->whereIn('status', DeviceStatusService::PROBLEM_STATUSES);
    }

    /** 這台設備的所有報修單（報修時有掃描或選擇設備的才會有 device_id）。 */
    public function repairRequests()
    {
        return $this->hasMany(RepairRequest::class);
    }

    /** 這台設備的類別；外鍵欄位不是預設的 category_id，所以要特別指定 device_category_id。 */
    public function category()
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }

    /** 這台設備所在的教室。 */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
