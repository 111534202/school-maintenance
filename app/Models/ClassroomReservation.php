<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ClassroomReservation extends Model
{
    protected $fillable = [
        'classroom_id',
        'requester_id',
        'class_name',
        'reservation_date',
        'start_time',
        'end_time',
        'status',
        'purpose',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(
            ClassroomReservationSlot::class,
            'classroom_reservation_id'
        )->orderBy('reservation_date')->orderBy('start_time');
    }
}