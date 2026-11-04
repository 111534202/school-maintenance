<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 保養計畫。
 *
 * @property int $id
 * @property string $name
 * @property int|null $device_id
 * @property int|null $device_category_id
 * @property int $cycle_days
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $next_due_date
 * @property bool $is_active
 */
#[Fillable(['name', 'device_id', 'device_category_id', 'cycle_days', 'start_date', 'next_due_date', 'is_active'])]
class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    // 第三正規化：已經指定了一台設備（device_id），類別由設備推得，不另存 device_category_id（儲存時自動清掉）。
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->device_id !== null) {
                $model->device_category_id = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'device_category_id' => 'integer',
            'cycle_days' => 'integer',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 對應設備（唯讀引用林政寬的 Device Model，含已軟刪除的設備，避免歷史資料查不到設備）。
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /**
     * 畫面顯示用的設備類別名稱：有設備就用設備本身的類別，沒有設備（只針對一類設備）才用自己的設備類別。
     */
    public function categoryName(): ?string
    {
        return $this->device?->category?->name ?? $this->deviceCategory?->name;
    }

    /** 設備類別：device_category_id 指向 device_categories（保養項目／計畫／工單要針對哪一類設備）。 */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    /**
     * 此計畫包含的保養項目（多對多，透過 maintenance_plan_items）。
     *
     * @return BelongsToMany<MaintenanceItem, $this>
     */
    public function maintenanceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceItem::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
