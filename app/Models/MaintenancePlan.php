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
    /**
     * 做完（或取消）一張工單之後，把計畫的「下次到期日」往後推一個週期，這樣排程才會每個週期自動產生下一張。
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

    public function maintenanceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceItem::class,
            'maintenance_plan_items'
        )->withTimestamps();
    }
}
