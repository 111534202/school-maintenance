<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 預防保養候選（第 4 週任務 2、3）。
 *
 * @property int $id
 * @property int $device_id
 * @property float $risk_score
 * @property float $threshold
 * @property string $algorithm
 * @property array|null $explanation
 * @property array|null $features
 * @property string $status
 * @property int|null $maintenance_order_id
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'risk_score',
    'threshold',
    'algorithm',
    'explanation',
    'features',
    'status',
    'maintenance_order_id',
    'decided_by',
    'decided_at',
    'decision_note',
])]
class PreventiveCandidate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_AUTO_CREATED = 'auto_created';

    public const STATUS_DUPLICATE = 'duplicate';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_score' => 'float',
            'threshold' => 'float',
            'explanation' => 'array',
            'features' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    /** @return BelongsTo<MaintenanceOrder, $this> */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return array{0: string, 1: string} [中文標籤, Bootstrap badge 顏色]
     */
    public function statusLabel(): array
    {
        return match ($this->status) {
            self::STATUS_APPROVED => ['已核准', 'success'],
            self::STATUS_REJECTED => ['已駁回', 'secondary'],
            self::STATUS_AUTO_CREATED => ['自動建單', 'info'],
            self::STATUS_DUPLICATE => ['重複未建單', 'dark'],
            default => ['待審核', 'warning'],
        };
    }
}
