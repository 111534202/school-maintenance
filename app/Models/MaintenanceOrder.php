<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 * @property int $id
 * @property int|null $maintenance_plan_id
 * @property int|null $device_id
 * @property string $source
 * @property string $status
 * @property int|null $assigned_to
 * @property \Illuminate\Support\Carbon|null $scheduled_date
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $device_category_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
#[Fillable([
    'maintenance_plan_id',
    'device_id',
    'device_category_id',
    'source',
    'status',
    'assigned_to',
    'scheduled_date',
    'started_at',
])]
class MaintenanceOrder extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceOrderFactory> */
    use HasFactory;
    use SoftDeletes;   // 取消／刪除＝軟刪除（deleted_at 有時間就是已取消，列表與統計不再列出，資料仍保留）

    // 工單來源：定期排程 / AI 辨識（本週僅會用到 SOURCE_PERIODIC，SOURCE_AI 由後續 AI 任務接手）
    public const SOURCE_PERIODIC = 'periodic';

    public const SOURCE_AI = 'ai';

    // 狀態（依《報修與保養流程修改說明》）：
    // 待處理（還沒派工）→ 確認派工後「進行中」→ 回報 OK「已完成」；回報 NG「已轉報修」，轉出的報修單全部結案（已結案或已退回）後才「已完成」。
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_COMPLETED = 'completed';

    // 已中止：設備淘汰時，還沒做完的工單自動中止（終點，和已完成一樣唯讀，不再列入完成率分母）。
    public const STATUS_ABORTED = 'aborted';

    /** 篩選與匯出用：狀態值 → 中文。 */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => '待處理',
        self::STATUS_IN_PROGRESS => '進行中',
        self::STATUS_CONVERTED => '已轉報修',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_ABORTED => '已中止',
    ];

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
            'started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MaintenancePlan, $this> */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * 對應設備（唯讀引用林政寬的 Device Model，含已軟刪除的設備，避免歷史工單查不到設備）。
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
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
     * 畫面顯示用的設備類別名稱：有設備就用設備本身的類別，沒有設備（只針對一類設備的工單）才用工單自己的設備類別。
     */
    public function categoryName(): ?string
    {
        return $this->device?->category?->name ?? $this->deviceCategory?->name;
    }

    /**
     * 這張工單的保養對象（好幾台設備或好幾個設備類別，每一列擇一），建立工單時從保養計畫複製。
     * device_id／device_category_id 存第一個對象（AI 預防保養工單只有一台設備）。
     *
     * @return HasMany<MaintenanceOrderTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(MaintenanceOrderTarget::class)->orderBy('id');
    }

    /**
     * 保養對象清單（給畫面用）：有對象清單就用清單，沒有（舊資料、測試直接建立的工單）就用工單本身的設備／類別。
     *
     * @return \Illuminate\Support\Collection<int, array{device: Device|null, category: DeviceCategory|null}>
     */
    public function targetItems(): \Illuminate\Support\Collection
    {
        $targets = $this->relationLoaded('targets') ? $this->targets : $this->targets()->with(['device.category', 'deviceCategory'])->get();
        if ($targets->isNotEmpty()) {
            return $targets->map(fn (MaintenanceOrderTarget $t) => ['device' => $t->device_id ? $t->device : null, 'category' => $t->device_id ? $t->device?->category : $t->deviceCategory]);
        }

        if ($this->device_id === null && $this->device_category_id === null) {
            return collect();
        }

        return collect([['device' => $this->device, 'category' => $this->device ? $this->device->category : $this->deviceCategory]]);
    }

    /** 畫面／匯出用的保養對象文字：「DEV-0001（投影機）、冷氣（整類）」。 */
    public function targetsLabel(): string
    {
        $items = $this->targetItems();
        if ($items->isEmpty()) {
            return '—';
        }

        return $items->map(fn (array $item) => $item['device']
            ? $item['device']->device_code . '（' . ($item['category']?->name ?? '—') . '）'
            : ($item['category']?->name ?? '—') . '（整類）')->implode('、');
    }

    /**
     * 依保養計畫建立這一期的工單（待處理、排定日期＝計畫的下次到期日）：計畫的所有保養對象都放進這張工單的對象清單，
     * 工單本身的 device_id／device_category_id 存第一個對象。
     */
    public static function createFromPlan(MaintenancePlan $plan): ?self
    {
        // 已淘汰或停用的設備不再保養：略過；全部對象都不能保養就不建立（回傳 null）。
        $targets = $plan->maintainableTargets();
        if ($targets === []) {
            return null;
        }
        $order = self::create([
            'maintenance_plan_id' => $plan->id,
            ...($targets[0] ?? ['device_id' => null, 'device_category_id' => null]),
            'source' => self::SOURCE_PERIODIC,
            'status' => self::STATUS_PENDING,
            'scheduled_date' => $plan->next_due_date,
        ]);
        foreach ($targets as $target) {
            if ($target['device_id'] !== null || $target['device_category_id'] !== null) {
                $order->targets()->create($target);
            }
        }

        return $order;
    }

    /** 查詢範圍：包含這台設備的工單（工單本身的設備，或對象清單裡有這台設備）。 */
    public function scopeCoveringDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where(fn ($q) => $q->where($q->qualifyColumn('device_id'), $deviceId)
            ->orWhereHas('targets', fn ($t) => $t->where('device_id', $deviceId)));
    }

    /** @return HasOne<MaintenanceResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(MaintenanceResult::class);
    }

    /**
     * 負責人（由保養管理者指派，沒指派是空白）。
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** 還沒做完（待處理、進行中）的工單。 */
    public function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /** 已定案、不能再變動的工單：已完成，或因設備淘汰而中止。 */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_ABORTED], true);
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

    /** 這張工單現在能不能「確認派工」：還在待處理（還沒派工），而且這位用戶有保養管理權限。 */
    public function canBeDispatchedBy(User $user): bool
    {
        return $this->status === self::STATUS_PENDING && $user->can('maintenance.manage');
    }

    /**
     * 這位用戶能不能回報這張工單的結果：工單要已經派工（進行中）、還沒回報，
     * 而且是負責人本人或保養管理者（都要有回報權限）。沒派工的工單要先「確認派工」才能回報。
     */
    public function canBeReportedBy(User $user): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS || ! $user->can('maintenance.report')) {
            return false;
        }

        return $this->assigned_to === $user->id || $user->can('maintenance.manage');
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
     * 工單編號（畫面上看得到、講得出口的編號）：定期保養是 MO-00012，AI 預防保養是 AI-00012。
     * 前綴由來源決定，所以一眼就能分辨這張工單是人建立的還是 AI 提出的；數字就是資料表的編號。
     */
    public function displayNumber(): string
    {
        return ($this->source === self::SOURCE_AI ? 'AI-' : 'MO-') . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
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
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => ['進行中', 'primary'],
            self::STATUS_CONVERTED => ['已轉報修', 'info'],
            self::STATUS_COMPLETED => ['已完成', 'success'],
            self::STATUS_ABORTED => ['已中止', 'dark'],
            default => ['待處理', 'warning'],
        };
    }
}
