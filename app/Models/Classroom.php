<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'campus', 'building', 'floor', 'room_code', 'room_name',
        'room_type', 'manager_id', 'is_active', 'reservation_status',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
