<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 預約申請裡的一個時段。status 是這個時段的審核結果（approved／rejected），可以逐節審核；
 * 空白代表還沒審核（或更早、沒有逐節結果的舊資料，跟著整筆申請的狀態）。
 */
class ClassroomReservationSlot extends Model
{
    protected $fillable = [
        'classroom_reservation_id',
        'reservation_date',
        'start_time',
        'end_time',
        'period_number',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'period_number' => 'integer',
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

    /** 節次名稱：0＝早自習、1～13＝第 N 節；沒有節次（例如午休或舊資料）回傳 null。 */
    public function periodLabel(): ?string
    {
        if ($this->period_number === null) {
            return null;
        }

        return $this->period_number === 0 ? '早自習' : "第 {$this->period_number} 節";
    }

    /**
     * 時段顯示文字：2026-11-03 第 2 節。畫面只顯示日期與節次；起訖時間仍然記在資料裡（用來判斷重疊、時間是否已過）。
     * 沒有節次的時段（例如午休或舊資料）才顯示起訖時間：2026-11-03 12:10–13:00。
     */
    public function label(): string
    {
        return $this->reservation_date->format('Y-m-d') . ' ' . $this->periodOrTime();
    }

    /** 節次（第 2 節）；沒有節次時用起訖時間代替。 */
    public function periodOrTime(): string
    {
        return $this->periodLabel() ?? (substr((string) $this->start_time, 0, 5) . '–' . substr((string) $this->end_time, 0, 5));
    }
}
