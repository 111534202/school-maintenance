<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 零件報廢紀錄：零件還沒用就壞掉、過期或遺失時登記，從庫存扣掉。
 * 扣的是哪幾個採購批次記在 batches（先進先出，跟維修、保養用料一樣），報廢損失＝各批數量×當時單價。
 * 新增與取消都要透過 PartStockService（scrap、restoreScrap），才能同步更新批次剩餘數量與目前庫存。
 *
 *
 * @property int $id
 * @property int $part_id
 * @property \Illuminate\Support\Carbon $scrap_date
 * @property string|null $scrap_time
 * @property int $quantity
 * @property string $reason
 * @property string|null $note
 * @property int|null $recorded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PartScrap extends Model
{
    /** 畫面上「原因」可以直接點選的常見選項（也可以自己輸入其他原因）。 */
    public const COMMON_REASONS = ['損壞', '故障', '過期', '遺失', '外觀破損'];

    protected $fillable = [
        'part_id',
        'scrap_date',
        'scrap_time',   // 報廢時間（同一天的使用與報廢依時間排先後；舊資料可能是空的）
        'quantity',
        'reason',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'scrap_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Part, $this> */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * 登記報廢的人。
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * 這次報廢扣了哪幾個採購批次。
     *
     * @return HasMany<PartScrapBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(PartScrapBatch::class);
    }

    /** 報廢損失（元）：各批數量×當時單價；有缺少單價的部分不算進去（hasMissingPrice() 會是 true）。 */
    public function lossAmount(): int
    {
        return (int) $this->batches->sum(fn (PartScrapBatch $batch) => $batch->quantity * ($batch->unit_price ?? 0));
    }

    public function hasMissingPrice(): bool
    {
        return $this->batches->contains(fn (PartScrapBatch $batch) => $batch->unit_price === null);
    }
}
