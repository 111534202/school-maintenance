<?php

namespace App\Models;

use Database\Factories\MaintenanceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 保養項目。
 *
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property int|null $default_cycle_days
 * @property bool $is_active
 */
#[Fillable(['name', 'category', 'description', 'default_cycle_days', 'is_active'])]
class MaintenanceItem extends Model
{
    /** @use HasFactory<MaintenanceItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'default_cycle_days' => 'integer',
            'is_active' => 'boolean',
        ];
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
