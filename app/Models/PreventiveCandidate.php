<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 預防保養候選（第 4 週任務 2、3）。
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

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
