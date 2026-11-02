<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 預約申請裡的一個時段。每個時段分別審核：status 是這個時段的結果（approved／rejected），
 * 空白代表跟著整筆申請的狀態（還沒審核、或是改成分時段審核以前的舊資料）。
 */
class ClassroomReservationSlot extends Model
{
    protected $fillable = [
        'classroom_reservation_id',
        'reservation_date',
        'start_time',
        'end_time',
        'period_no',
        'status',
        'rejection_reason',
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

    /** 這個時段實際的狀態：有自己的審核結果就用自己的，沒有就跟著整筆申請（部分核准的申請裡不會有空白的時段）。 */
    public function effectiveStatus(): string
    {
        return $this->status ?? $this->reservation?->status ?? ClassroomReservation::STATUS_PENDING;
    }

    /** 時段顯示文字：2026-11-03 09:10–10:00（第 2 節）。 */
    public function label(): string
    {
        return $this->reservation_date->format('Y-m-d') . ' ' . substr((string) $this->start_time, 0, 5) . '–' . substr((string) $this->end_time, 0, 5)
            . ($this->period_no ? "（第 {$this->period_no} 節）" : '');
    }
}
