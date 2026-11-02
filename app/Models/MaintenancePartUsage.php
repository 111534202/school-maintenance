<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 保養用料明細：保養時用掉的零件（欄位與維修用料 RepairPartUsage 相同）。
 * unit_price 是扣料當下那一批採購的單價（先進先出）；跨兩批時會拆成兩列。
 */
class MaintenancePartUsage extends Model
{
    protected $fillable = [
        'part_id',
        'part_purchase_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
    ];

    public function maintenanceResult(): BelongsTo
    {
        return $this->belongsTo(MaintenanceResult::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PartPurchase::class, 'part_purchase_id');
    }
}
