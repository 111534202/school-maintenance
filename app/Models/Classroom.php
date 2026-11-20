<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;   // 「軟刪除」功能：刪除只是標記時間，資料還在，可以還原

/**
 * 教室（classrooms 資料表）。教室底下有設備；每間教室有所屬部門與管理人。
 * 「異常設備」＝有未結束報修單的設備（不含已淘汰），見 Device::scopeAbnormal；教室不會因此暫停預約。
 *
 *
 * @property int $id
 * @property int|null $department_id
 * @property string $campus
 * @property string $building
 * @property string $floor
 * @property string $classroom_code
 * @property string $name
 * @property string|null $type
 * @property int|null $manager_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Classroom extends Model
{
    use SoftDeletes;   // 啟用軟刪除（資料表要有 deleted_at 欄位，見對應的 migration）

    // 允許批次寫入的欄位白名單。
    protected $fillable = [
        'department_id', 'campus', 'building', 'floor', 'classroom_code', 'name',
        'type', 'manager_id', 'is_active',
    ];

    // 型別轉換：資料庫存 0/1，這裡自動轉成 PHP 的 true/false。
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 教室所屬的部門（一間教室屬於一個部門）。
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * 教室的管理人（用戶）；外鍵欄位不是預設的 user_id，所以要特別指定 manager_id。
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** 查詢範圍：只撈「有異常設備」的教室（有未結束報修單的設備，定義見 Device::scopeAbnormal）。 */
    public function scopeWithAbnormalDevices(Builder $query): Builder
    {
        return $query->whereHas('devices', fn ($devices) => $devices->abnormal());
    }

    /**
     * 這間教室裡所有設備的報修單（教室 → 設備 → 報修單，靠 repair_requests.device_id 串起來）。
     * 注意：只有「報修時有綁定設備」的報修單算在內；手動輸入文字描述、沒有設備的報修單不會出現在這裡。
     *
     * @return HasManyThrough<RepairRequest, Device, $this>
     */
    public function repairRequests(): HasManyThrough
    {
        return $this->hasManyThrough(RepairRequest::class, Device::class);
    }

    /**
     * 這間教室裡的所有設備（一間教室有很多設備）。
     *
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
