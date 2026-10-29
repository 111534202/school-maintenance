<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'maintenance_plan_id',
    'device_id',
    'device_category',
    'source',
    'status',
    'scheduled_date',
])]
class MaintenanceOrder extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceOrderFactory> */
    use HasFactory;

    // 工單來源：定期排程 / AI 辨識（本週僅會用到 SOURCE_PERIODIC，SOURCE_AI 由後續 AI 任務接手）
    public const SOURCE_PERIODIC = 'periodic';

    public const SOURCE_AI = 'ai';

    // 基本狀態：待處理 / 進行中 / 已完成（完整狀態流程與保養結果的串接由第2週任務接手）
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

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

    public function result(): HasOne
    {
        return $this->hasOne(MaintenanceResult::class);
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
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => ['進行中', 'primary'],
            self::STATUS_COMPLETED => ['已完成', 'success'],
            default => ['待處理', 'warning'],
        };
    }
}
