<?php

namespace Tests\Concerns;

use App\Models\ClassroomReservation;

/**
 * 測試用：建立一筆教室預約申請，連同一個時段。
 * 使用時間只記在時段明細（classroom_reservation_slots），所以傳進來的 reservation_date／start_time／end_time
 * 會變成這筆申請的第一個時段（可再傳 period_number）；其餘欄位照原樣寫進申請本身。
 */
trait CreatesReservations
{
    protected function makeReservation(array $attributes): ClassroomReservation
    {
        $slotKeys = ['reservation_date', 'start_time', 'end_time', 'period_number'];
        $slot = array_intersect_key($attributes, array_flip($slotKeys));
        $reservation = ClassroomReservation::create(array_diff_key($attributes, array_flip($slotKeys)));

        if (isset($slot['reservation_date'], $slot['start_time'], $slot['end_time'])) {
            $reservation->slots()->create($slot + ['period_number' => null]);
        }

        return $reservation;
    }
}
