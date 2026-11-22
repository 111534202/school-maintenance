<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 保養計畫。
 *
 *
 *
 * @property int $id
 * @property string $name
 * @property string|null $description 保養說明（取代原本勾選的保養項目）
 * @property int|null $device_id
 * @property int $cycle_days
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $next_due_date
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $device_category_id
 */
#[Fillable(['name', 'description', 'device_id', 'device_category_id', 'cycle_days', 'start_date', 'next_due_date', 'is_active'])]
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
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /**
     * 這份計畫是不是已被鎖定（唯讀）：綁定的設備已淘汰。淘汰時計畫會一併停用，之後不能修改、不能重新啟用。
     */
    public function isLocked(): bool
    {
        return $this->device_id !== null && $this->device?->status === 'retired';
    }

    /**
     * 畫面顯示用的設備類別名稱：有設備就用設備本身的類別，沒有設備（只針對一類設備）才用自己的設備類別。
     */
    public function categoryName(): ?string
    {
        return $this->device?->category?->name ?? $this->deviceCategory?->name;
    }

    /**
     * 設備類別：device_category_id 指向 device_categories（保養項目／計畫／工單要針對哪一類設備）。
     *
     * @return BelongsTo<DeviceCategory, $this>
     */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    /**
     * 保養對象（好幾台設備或好幾個設備類別，每一列擇一）。計畫上的 device_id／device_category_id 存第一個對象，給舊的程式讀。
     *
     * @return HasMany<MaintenancePlanTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(MaintenancePlanTarget::class)->orderBy('id');
    }

    /**
     * 建立工單用的對象清單：每個對象一筆 [device_id, device_category_id]（一張工單一筆）。
     * 還沒有設定對象清單的舊計畫（或測試直接建立的計畫）就用計畫本身的設備／類別。
     *
     * @return list<array{device_id: int|null, device_category_id: int|null}>
     */
    public function orderTargets(): array
    {
        $targets = $this->relationLoaded('targets') ? $this->targets : $this->targets()->get();
        if ($targets->isEmpty()) {
            return [['device_id' => $this->device_id, 'device_category_id' => $this->device_id ? null : $this->device_category_id]];
        }

        return $targets->map(fn (MaintenancePlanTarget $target) => ['device_id' => $target->device_id, 'device_category_id' => $target->device_category_id])->all();
    }

    /**
     * 還能保養的對象：指定的設備已淘汰或停用（Device::canBeMaintained）就略過；設備類別照常（NG 時只能選還在用的設備）。
     *
     * @return list<array{device_id: int|null, device_category_id: int|null}>
     */
    public function maintainableTargets(): array
    {
        return array_values(array_filter($this->orderTargets(), fn (array $target) => $target['device_id'] === null
            || Device::canBeMaintained($target['device_id'])));
    }

    /** 畫面顯示的保養對象（用「、」串起來）；沒有對象清單時用計畫本身的設備／類別。 */
    public function targetsLabel(): string
    {
        $targets = $this->relationLoaded('targets') ? $this->targets : $this->targets()->with(['device.category', 'deviceCategory'])->get();
        if ($targets->isNotEmpty()) {
            return $targets->map(fn (MaintenancePlanTarget $target) => $target->label())->implode('、');
        }
        if ($this->device) {
            return $this->device->device_code . '（' . ($this->categoryName() ?? '—') . '）';
        }

        return $this->deviceCategory ? $this->deviceCategory->name . '（整類）' : '—';
    }

    /** 還能不能產生工單：計畫的對象裡至少還有一個能保養（指定的設備全都淘汰或停用就不行；設備類別照常）。 */
    public function canGenerateOrders(): bool
    {
        return $this->maintainableTargets() !== [];
    }

    /**
     * 做完（回報結果）或取消一張工單之後，把計畫的「下次到期日」往後推一個週期，排程到了新的到期日才會產生下一張。
     *
     * 只在這張工單就是計畫目前這一期（排定日 ＝ 下次到期日）時才推，避免補做舊工單或手動建的工單亂改日期。
     * 新的到期日 ＝ 這張工單的排定日 ＋ 週期天數；如果已經過了今天（很久沒做），就繼續往後加到今天以後，不會一次補產生一堆過期工單。
     */
    public function advanceDueDateAfter(MaintenanceOrder $order): void
    {
        if ($this->next_due_date === null || $this->cycle_days < 1 || $order->scheduled_date === null) {
            return;
        }
        if ($order->scheduled_date->toDateString() !== $this->next_due_date->toDateString()) {
            return;
        }

        $next = $order->scheduled_date->copy()->addDays($this->cycle_days);
        while ($next->lt(now()->startOfDay())) {
            $next->addDays($this->cycle_days);
        }

        $this->update(['next_due_date' => $next->toDateString()]);
    }

    /** @return BelongsToMany<MaintenanceItem, $this> */
    public function maintenanceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceItem::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
