<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomReservationSlot extends Model
{
    protected $fillable = [
        'classroom_reservation_id',
        'reservation_date',
        'start_time',
        'end_time',
        'period_no',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'period_no' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ClassroomReservation::class, 'classroom_reservation_id');
    }


}