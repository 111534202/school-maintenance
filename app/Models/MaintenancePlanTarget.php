<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 保養計畫的一個保養對象：一台設備，或一個設備類別（兩者擇一）。一個計畫可以有好幾個對象，
 * 建立工單時每個對象各建一張（見 MaintenancePlan::orderTargets()）。
 *
 * @property int $id
 * @property int $maintenance_plan_id
 * @property int|null $device_id
 * @property int|null $device_category_id
 */
#[Fillable(['maintenance_plan_id', 'device_id', 'device_category_id'])]
class MaintenancePlanTarget extends Model
{
    /** @return BelongsTo<MaintenancePlan, $this> */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /** @return BelongsTo<DeviceCategory, $this> */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    /** 表單選單用的值：device:編號 或 category:編號。 */
    public function value(): string
    {
        return $this->device_id ? 'device:' . $this->device_id : 'category:' . $this->device_category_id;
    }

    /** 畫面顯示：設備顯示「設備編號（類別）」，類別顯示「類別名稱（整類）」。 */
    public function label(): string
    {
        if ($this->device_id) {
            return ($this->device?->device_code ?? '—') . '（' . ($this->device?->category?->name ?? '—') . '）';
        }

        return ($this->deviceCategory?->name ?? '—') . '（整類）';
    }
}
