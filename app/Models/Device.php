<?php

namespace App\Models;

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
