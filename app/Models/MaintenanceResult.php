<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'maintenance_order_id',
    'result',
    'executed_by',
    'executed_at',
    'notes',
    'ng_conversion_status',
    'repair_request_id',
])]
class MaintenanceResult extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceResultFactory> */
    use HasFactory;

    public const RESULT_OK = 'ok';

    public const RESULT_NG = 'ng';

    // NG 轉報修狀態（工程實作欄位，待彭仕衡提供正式介面後可能整個替換掉）
    public const NG_CONVERSION_PENDING = 'pending_handoff';

    public const NG_CONVERSION_NOT_APPLICABLE = 'not_applicable';

    /** 已呼叫彭仕衡的 Action 建立報修單，repair_request_id 為該報修單。 */
    public const NG_CONVERSION_CONVERTED = 'converted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function isNg(): bool
    {
        return $this->result === self::RESULT_NG;
    }

    /**
     * @return array{0: string, 1: string} [中文標籤, Bootstrap badge 顏色]
     */
    public function resultLabel(): array
    {
        return $this->isNg() ? ['NG 異常', 'danger'] : ['OK 正常', 'success'];
    }
}
