<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 一次報廢扣的某一個採購批次（數量與那一批的單價）。見 PartScrap。 */
class PartScrapBatch extends Model
{
    protected $fillable = [
        'part_purchase_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function scrap(): BelongsTo
    {
        return $this->belongsTo(PartScrap::class, 'part_scrap_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PartPurchase::class, 'part_purchase_id');
    }
}
