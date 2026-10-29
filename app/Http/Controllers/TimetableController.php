<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\ClassroomReservationSlot;
use App\Services\Timetable\TimetableProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimetableController extends Controller
{
    public function day(Request $request, TimetableProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'classroom_id.required' => "\u{8ACB}\u{5148}\u{9078}\u{64C7}\u{6559}\u{5BA4}\u{3002}",
            'date.required' => "\u{8ACB}\u{5148}\u{9078}\u{64C7}\u{65E5}\u{671F}\u{3002}",
            'date.after_or_equal' => "\u{67E5}\u{8A62}\u{65E5}\u{671F}\u{4E0D}\u{53EF}\u{65E9}\u{65BC}\u{4ECA}\u{5929}\u{3002}",
        ]);

        $classroom = Classroom::query()
            ->whereKey($validated['classroom_id'])
            ->where('is_active', true)
            ->where('reservation_status', 'normal')
            ->first();

        if (! $classroom) {
            return response()->json([
                'message' => "\u{9019}\u{9593}\u{6559}\u{5BA4}\u{76EE}\u{524D}\u{5DF2}\u{505C}\u{7528}\u{6216}\u{66AB}\u{505C}\u{5916}\u{501F}\u{3002}",
            ], 422);
        }

        $date = Carbon::createFromFormat('!Y-m-d', $validated['date']);

        $reservations = $this->reservationsForDate(
            $classroom->id,
            $validated['date']
        );

        $schedule = $provider->getDaySchedule($classroom, $date);

        foreach ($schedule as &$period) {
            $periodStart = $period['start_time'];
            $periodEnd = $period['end_time'];

            $period['reservations'] = $reservations
                ->filter(function ($reservation) use ($periodStart, $periodEnd) {
                    return substr((string) $reservation->start_time, 0, 5) < $periodEnd
                        && substr((string) $reservation->end_time, 0, 5) > $periodStart;
                })
                ->map(function ($reservation) {
                    return [
                        'class_name' => $reservation->class_name,
                        'start_time' => substr((string) $reservation->start_time, 0, 5),
                        'end_time' => substr((string) $reservation->end_time, 0, 5),
                        'status' => $reservation->status,
                    ];
                })
                ->values()
                ->all();
        }
        unset($period);

        return response()->json([
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'classroom_code' => $classroom->classroom_code,
            ],
            'date' => $validated['date'],
            'periods' => $schedule,
        ]);
    }

    /**
     * 回傳指定日期所在週的星期一至星期五課表。
     */
    public function week(Request $request, TimetableProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'classroom_id.required' => '請先選擇教室。',
            'date.required' => '請先選擇日期。',
            'date.after_or_equal' => '查詢日期不可早於今天。',
        ]);

        $classroom = Classroom::query()
            ->whereKey($validated['classroom_id'])
            ->where('is_active', true)
            ->where('reservation_status', 'normal')
            ->first();

        if (! $classroom) {
            return response()->json([
                'message' => '這間教室目前已停用或暫停外借。',
            ], 422);
        }

        $selectedDate = Carbon::createFromFormat('!Y-m-d', $validated['date']);
        $monday = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $days = [];

        for ($offset = 0; $offset < 5; $offset++) {
            $date = $monday->copy()->addDays($offset);
            $dateString = $date->toDateString();

            $reservations = $this->reservationsForDate(
                $classroom->id,
                $dateString
            );

            $schedule = $provider->getDaySchedule($classroom, $date);

            foreach ($schedule as &$period) {
                $periodStart = $period['start_time'];
                $periodEnd = $period['end_time'];

                $period['reservations'] = $reservations
                    ->filter(function ($reservation) use ($periodStart, $periodEnd) {
                        return substr((string) $reservation->start_time, 0, 5) < $periodEnd
                            && substr((string) $reservation->end_time, 0, 5) > $periodStart;
                    })
                    ->map(function ($reservation) {
                        return [
                            'class_name' => $reservation->class_name,
                            'start_time' => substr((string) $reservation->start_time, 0, 5),
                            'end_time' => substr((string) $reservation->end_time, 0, 5),
                            'status' => $reservation->status,
                        ];
                    })
                    ->values()
                    ->all();
            }
            unset($period);

            $days[] = [
                'date' => $dateString,
                'weekday' => $date->isoFormat('dddd'),
                'periods' => $schedule,
            ];
        }

        return response()->json([
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'classroom_code' => $classroom->classroom_code,
            ],
            'selected_date' => $selectedDate->toDateString(),
            'week_start' => $monday->toDateString(),
            'week_end' => $monday->copy()->addDays(4)->toDateString(),
            'days' => $days,
        ]);
    }

    /**
     * 依實際預約時段取得指定日期的待審核與已核准預約。
     */
    private function reservationsForDate(int $classroomId, string $dateString)
    {
        $slotReservations = ClassroomReservationSlot::query()
            ->whereDate('reservation_date', $dateString)
            ->whereHas('reservation', function ($query) use ($classroomId) {
                $query->where('classroom_id', $classroomId)
                    ->whereIn('status', ['pending', 'approved']);
            })
            ->with('reservation:id,classroom_id,class_name,status')
            ->orderBy('start_time')
            ->get()
            ->map(function ($slot) {
                return (object) [
                    'class_name' => $slot->reservation->class_name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'status' => $slot->reservation->status,
                ];
            });

        // 相容尚未建立時段明細的舊資料。
        $legacyReservations = ClassroomReservation::query()
            ->where('classroom_id', $classroomId)
            ->whereDate('reservation_date', $dateString)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDoesntHave('slots')
            ->get(['id', 'class_name', 'start_time', 'end_time', 'status']);

        return $slotReservations
            ->concat($legacyReservations)
            ->sortBy('start_time')
            ->values();
    }}
