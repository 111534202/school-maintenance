<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $fillable = [
        'name', // 零件名稱
        'specification', // 零件規格
        'unit_price', // 單價
        'current_stock', // 目前庫存
        'safety_stock', // 安全庫存
    ];
}
