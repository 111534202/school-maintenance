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
            'cycle_days' => 'integer',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 此計畫包含的保養項目（多對多，透過 maintenance_plan_items）。
     *
     * @return BelongsToMany<MaintenanceItem, $this>
     */
    /**
     * 此計畫指定的設備。
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /**
     * 此計畫指定的設備類別。
     */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }
    public function maintenanceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceItem::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
