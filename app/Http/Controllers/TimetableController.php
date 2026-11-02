<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\ClassroomReservationSlot;
use App\Models\RepairRequest;
use App\Enums\RepairRequestStatus;
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
            ->where('is_active', true)   // 暫停預約（設備異常）的教室仍可查詢課表，只是不能送出申請
            ->first();

        if (! $classroom) {
            return response()->json([
                'message' => '這間教室目前已停用。',
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
            ->where('is_active', true)   // 暫停預約（設備異常）的教室仍可查詢課表，只是不能送出申請
            ->first();

        if (! $classroom) {
            return response()->json([
                'message' => '這間教室目前已停用。',
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
     * 派工用：回傳這張報修單「設備地點」那間教室一週（星期一至五）的課表，讓主管挑空堂當建議處理時間。
     *
     * 和預約用的 week() 不同：
     * - 教室是報修單的地點（沒有就用設備所在的教室），不用另外選；
     * - 「設備異常」的教室也要能看（需要維修的通常就是這種教室），停用的教室也照樣顯示；
     * - 每一節另外列出同一間教室已經排定的其他維修（避免兩張單排在同一節）。
     */
    public function repairWeek(Request $request, RepairRequest $repairRequest, TimetableProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $repairRequest->loadMissing('classroom', 'device.classroom');
        $classroom = $repairRequest->classroom ?? $repairRequest->device?->classroom;

        if (! $classroom) {
            return response()->json(['message' => '這張報修單沒有設備地點，無法查詢教室課表；請直接填寫建議處理時間。'], 422);
        }

        $selectedDate = Carbon::createFromFormat('!Y-m-d', $validated['date'] ?? now()->toDateString());
        $monday = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $days = [];

        for ($offset = 0; $offset < 5; $offset++) {
            $date = $monday->copy()->addDays($offset);
            $dateString = $date->toDateString();
            $reservations = $this->reservationsForDate($classroom->id, $dateString);

            // 同一間教室、同一天已經排定處理時間、還沒結案的其他報修單。
            $otherRepairs = RepairRequest::query()
                ->whereKeyNot($repairRequest->id)
                ->whereDate('scheduled_at', $dateString)
                ->whereIn('status', [RepairRequestStatus::Assigned, RepairRequestStatus::InProgress])
                ->where(fn ($q) => $q->where('classroom_id', $classroom->id)
                    ->orWhereHas('device', fn ($d) => $d->where('classroom_id', $classroom->id)))
                ->get(['id', 'title', 'scheduled_at']);

            $schedule = $provider->getDaySchedule($classroom, $date);

            foreach ($schedule as &$period) {
                $start = $period['start_time'];
                $end = $period['end_time'];

                $period['reservations'] = $reservations
                    ->filter(fn ($r) => substr((string) $r->start_time, 0, 5) < $end && substr((string) $r->end_time, 0, 5) > $start)
                    ->map(fn ($r) => ['class_name' => $r->class_name, 'status' => $r->status])
                    ->values()->all();

                $period['repairs'] = $otherRepairs
                    ->filter(fn ($r) => $r->scheduled_at->format('H:i') >= $start && $r->scheduled_at->format('H:i') < $end)
                    ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title])
                    ->values()->all();
            }
            unset($period);

            $days[] = ['date' => $dateString, 'weekday' => $date->isoFormat('dddd'), 'periods' => $schedule];
        }

        return response()->json([
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'classroom_code' => $classroom->classroom_code],
            'selected_date' => $selectedDate->toDateString(),
            'week_start' => $monday->toDateString(),
            'week_end' => $monday->copy()->addDays(4)->toDateString(),
            'today' => now()->toDateString(),
            'days' => $days,
        ]);
    }

    /**
     * 依實際預約時段取得指定日期的待審核與已核准預約。
     */
    private function reservationsForDate(int $classroomId, string $dateString)
    {
        // 各時段的實際狀態：時段有自己的審核結果就用它，沒有就跟著整筆申請（見 ClassroomReservationSlot::effectiveStatus）。
        // 被拒絕的時段不顯示；部分核准的申請只顯示被同意的時段。
        $slotReservations = ClassroomReservationSlot::query()
            ->whereDate('reservation_date', $dateString)
            ->whereHas('reservation', function ($query) use ($classroomId) {
                $query->where('classroom_id', $classroomId)
                    ->whereIn('status', ['pending', 'approved', 'partially_approved']);
            })
            ->with('reservation:id,classroom_id,class_name,status')
            ->orderBy('start_time')
            ->get()
            ->filter(fn ($slot) => in_array($slot->effectiveStatus(), ['pending', 'approved'], true))
            ->map(function ($slot) {
                return (object) [
                    'class_name' => $slot->reservation->class_name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'status' => $slot->effectiveStatus(),
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
