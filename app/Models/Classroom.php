<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;   // 「軟刪除」功能：刪除只是標記時間，資料還在，可以還原

/**
 * 教室（classrooms 資料表）。教室底下有設備；每間教室有所屬部門與管理人。
 * 教室的 reservation_status 欄位是「設備異常旗標」：有核心設備異常時會被標成 abnormal，
 * 由 App\Services\DeviceStatusService 自動維護，不要手動改。
 */
class Classroom extends Model
{
    use SoftDeletes;   // 啟用軟刪除（資料表要有 deleted_at 欄位，見對應的 migration）

    // 允許批次寫入的欄位白名單。
    protected $fillable = [
        'department_id', 'campus', 'building', 'floor', 'room_code', 'room_name',
        'room_type', 'manager_id', 'is_active', 'reservation_status',
    ];

    // 型別轉換：資料庫存 0/1，這裡自動轉成 PHP 的 true/false。
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** 教室所屬的部門（一間教室屬於一個部門）。 */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** 教室的管理人（用戶）；外鍵欄位不是預設的 user_id，所以要特別指定 manager_id。 */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** 查詢範圍：只撈「有異常設備」的教室（異常的定義見 DeviceStatusService::PROBLEM_STATUSES）。 */
    public function scopeWithAbnormalDevices(Builder $query): Builder
    {
        return $query->whereHas('devices', fn ($devices) => $devices->abnormal());
    }

    /**
     * 這間教室裡所有設備的報修單（教室 → 設備 → 報修單，靠 repair_requests.device_id 串起來）。
     * 注意：只有「報修時有綁定設備」的報修單算在內；手動輸入文字描述、沒有設備的報修單不會出現在這裡。
     */
    public function repairRequests()
    {
        return $this->hasManyThrough(RepairRequest::class, Device::class);
    }

    /** 這間教室裡的所有設備（一間教室有很多設備）。 */
    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
