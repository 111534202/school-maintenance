<?php

namespace App\Models;

use Database\Factories\MaintenanceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 保養項目。
 *
 * @property int $id
 * @property string $name
 * @property int|null $device_category_id
 * @property string|null $description
 * @property int|null $default_cycle_days
 * @property bool $is_active
 */
#[Fillable(['name', 'device_category_id', 'description', 'default_cycle_days', 'is_active'])]
class MaintenanceItem extends Model
{
    /** @use HasFactory<MaintenanceItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'device_category_id' => 'integer',
            'default_cycle_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** 設備類別：device_category_id 指向 device_categories（保養項目／計畫／工單要針對哪一類設備）。 */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    /**
     * 使用此項目的保養計畫（多對多，透過 maintenance_plan_items）。
     *
     * @return BelongsToMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenancePlan::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
