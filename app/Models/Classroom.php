<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'department_id', 'campus', 'building', 'floor', 'room_code', 'room_name',
        'room_type', 'manager_id', 'is_active', 'reservation_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
