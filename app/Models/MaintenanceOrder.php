<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'maintenance_plan_id',
    'device_id',
    'device_category_id',
    'source',
    'status',
    'assigned_to',
    'scheduled_date',
])]
class MaintenanceOrder extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceOrderFactory> */
    use HasFactory;
    use SoftDeletes;   // 取消／刪除＝軟刪除（deleted_at 有時間就是已取消，列表與統計不再列出，資料仍保留）

    // 工單來源：定期排程 / AI 辨識（本週僅會用到 SOURCE_PERIODIC，SOURCE_AI 由後續 AI 任務接手）
    public const SOURCE_PERIODIC = 'periodic';

    public const SOURCE_AI = 'ai';

    // 基本狀態：待處理 / 進行中 / 已完成（完整狀態流程與保養結果的串接由第2週任務接手）
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    // 第三正規化：已經指定了一台設備（device_id），類別由設備推得，不另存 device_category_id（儲存時自動清掉）。
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->device_id !== null) {
                $model->device_category_id = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * 對應設備（唯讀引用林政寬的 Device Model，含已軟刪除的設備，避免歷史工單查不到設備）。
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /** 設備類別：device_category_id 指向 device_categories（保養項目／計畫／工單要針對哪一類設備）。 */
    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    /**
     * 畫面顯示用的設備類別名稱：有設備就用設備本身的類別，沒有設備（只針對一類設備的工單）才用工單自己的設備類別。
     */
    public function categoryName(): ?string
    {
        return $this->device?->category?->name ?? $this->deviceCategory?->name;
    }

    public function result(): HasOne
    {
        return $this->hasOne(MaintenanceResult::class);
    }

    /** 負責人（由保養管理者指派，沒指派是空白）。 */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** 還沒做完（待處理、進行中）的工單。 */
    public function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /** 逾期：還沒做完，而且排定保養日期早於今天。 */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unfinished()->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<', now()->toDateString());
    }

    /** 一週內到期（含已逾期）：還沒做完，而且排定保養日期在今天起算 $days 天以內。 */
    public function scopeDueWithin(Builder $query, int $days = 7): Builder
    {
        return $query->unfinished()->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', now()->addDays($days)->toDateString());
    }

    /** 這張工單是不是已經逾期。 */
    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true)
            && $this->scheduled_date !== null
            && $this->scheduled_date->toDateString() < now()->toDateString();
    }

    /** 這位用戶能不能回報這張工單的結果：沒指派負責人時有回報權限就行；指派了只有負責人與保養管理者。 */
    public function canBeReportedBy(User $user): bool
    {
        if (! $user->can('maintenance.report')) {
            return false;
        }

        return $this->assigned_to === null || $this->assigned_to === $user->id || $user->can('maintenance.manage');
    }

    /**
     * 給設備履歷、Dashboard 等其他模組使用的穩定查詢介面（第 2 週任務 6）：
     * 只回傳已完成的保養工單，不用讓其他模組自己拼 where 條件。
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * 畫面顯示用的中文標籤，統一放在 Model 上避免每個 view 各寫一份。
     */
    public function sourceLabel(): string
    {
        return $this->source === self::SOURCE_AI ? 'AI 辨識' : '定期';
    }

    /**
     * 來源標籤的 Bootstrap badge 顏色：AI 辨識用醒目的 info 色，定期用灰色（第 4 週任務 5）。
     */
    public function sourceColor(): string
    {
        return $this->source === self::SOURCE_AI ? 'info' : 'secondary';
    }

    /**
     * @return array{0: string, 1: string} [中文標籤, Bootstrap badge 顏色]
     */
    public function statusLabel(): array
    {
        // 保養結果是 NG 的工單不顯示「已完成」，直接顯示去向：已轉成報修單（或還沒轉成）；後續維修由報修單追蹤。
        if ($this->status === self::STATUS_COMPLETED && ($result = $this->result) && $result->isNg()) {
            return $result->ng_conversion_status === MaintenanceResult::NG_CONVERSION_CONVERTED
                ? ['已轉報修', 'info']
                : ['待轉報修', 'warning'];
        }

        return match ($this->status) {
            self::STATUS_IN_PROGRESS => ['進行中', 'primary'],
            self::STATUS_COMPLETED => ['已完成', 'success'],
            default => ['待處理', 'warning'],
        };
    }
}
