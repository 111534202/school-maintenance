<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairPartUsage extends Model
{
    // 資料表名稱統一用複數（舊名 repair_part_usage 由 2026_10_15_100000 migration 改名）。
    protected $table = 'repair_part_usages';

    protected $fillable = [
        'repair_log_id',
        'part_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
    ];

    /**
     * 取得這筆零件使用明細所屬的維修紀錄。
     */
    public function repairLog(): BelongsTo
    {
        return $this->belongsTo(RepairLog::class, 'repair_log_id');
    }

    /**
     * 取得這筆明細使用的零件。
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }
}
