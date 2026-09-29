<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 保養計畫。
 *
 * @property int $id
 * @property string $name
 * @property int|null $device_id
 * @property string|null $device_category
 * @property int $cycle_days
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $next_due_date
 * @property bool $is_active
 */
#[Fillable(['name', 'device_id', 'device_category', 'cycle_days', 'start_date', 'next_due_date', 'is_active'])]
class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

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
    public function maintenanceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceItem::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
