<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    protected $fillable = [
        'name',
        'specification',
        'unit_price',
        'current_stock',
        'safety_stock',
    ];

    /**
     * 取得這個零件所有的維修使用明細。
     */
    public function repairPartUsages(): HasMany
    {
        return $this->hasMany(RepairPartUsage::class, 'part_id');
    }
}
