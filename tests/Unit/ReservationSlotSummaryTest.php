<?php

namespace Tests\Unit;

use App\Models\ClassroomReservation;
use App\Models\ClassroomReservationSlot;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** 列表「申請時段」的簡短寫法：單節「11/13 第 6 節」；跨日連續 3 天以上「11/5~7」、其他「11/5,6」。 */
class ReservationSlotSummaryTest extends TestCase
{
    private function summary(array $slots): array
    {
        $reservation = new ClassroomReservation();
        $reservation->setRelation('slots', collect($slots)->map(function ($slot) {
            [$date, $period] = $slot;

            return (new ClassroomReservationSlot())->forceFill([
                'reservation_date' => Carbon::parse($date),
                'period_number' => $period,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
            ]);
        }));

        return $reservation->slotSummary();
    }

    public function test_single_slot_shows_date_and_period(): void
    {
        [$text, $count] = $this->summary([['2026-11-13', 6]]);
        $this->assertSame(1, $count);
        $this->assertStringStartsWith('11/13 ', $text);
        $this->assertStringContainsString('第 6 節', $text);
    }

    public function test_same_day_multiple_slots_shows_only_the_date(): void
    {
        $this->assertSame(['11/13', 3], $this->summary([['2026-11-13', 5], ['2026-11-13', 6], ['2026-11-13', 7]]));
    }

    public function test_consecutive_days_use_a_range_and_others_use_commas(): void
    {
        $this->assertSame(['11/5~7', 3], $this->summary([['2026-11-05', 1], ['2026-11-06', 1], ['2026-11-07', 1]]));
        $this->assertSame(['11/5,6', 2], $this->summary([['2026-11-05', 1], ['2026-11-06', 1]]));
        $this->assertSame(['11/5,9', 3], $this->summary([['2026-11-05', 1], ['2026-11-05', 2], ['2026-11-09', 1]]));
        $this->assertSame(['11/2~4,9', 4], $this->summary([['2026-11-02', 1], ['2026-11-03', 1], ['2026-11-04', 1], ['2026-11-09', 1]]));
        $this->assertSame(['10/30~11/2', 4], $this->summary([['2026-10-30', 1], ['2026-10-31', 1], ['2026-11-01', 1], ['2026-11-02', 1]]));
    }
}
