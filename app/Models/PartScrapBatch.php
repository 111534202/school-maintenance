<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一次報廢扣的某一個採購批次（數量與那一批的單價）。見 PartScrap。
 *
 *
 * @property int $id
 * @property int $part_scrap_id
 * @property int|null $part_purchase_id
 * @property int $quantity
 * @property int|null $unit_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return BelongsTo<PartScrap, $this> */
    public function scrap(): BelongsTo
    {
        return $this->belongsTo(PartScrap::class, 'part_scrap_id');
    }

    /** @return BelongsTo<PartPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PartPurchase::class, 'part_purchase_id');
    }
}
