<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassroomReservationController extends Controller
{
    /**
     * 預約列表：
     * 一般使用者只看自己的申請；
     * 有預約管理權限的人可以查看全部申請。
     */
    public function index(Request $request)
    {
        $query = ClassroomReservation::with(['classroom', 'requester', 'slots'])
            ->orderByDesc('reservation_date')
            ->orderBy('start_time');

        if (! $request->user()->hasPermission('reservations.manage')) {
            $query->where('requester_id', $request->user()->id);
        }

        $reservations = $query->paginate(15);

        return view('reservations.index', compact('reservations'));
    }

    /**
     * 顯示新增預約表單。
     * 只有啟用且正常開放外借的教室可以選擇。
     */
    public function create()
    {
        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->where('reservation_status', 'normal')
            ->orderBy('name')
            ->get();

        return view('reservations.create', compact('classrooms'));
    }

    /**
     * 儲存預約申請，並檢查是否與既有申請撞期。
     */
    public function store(Request $request)
    {
        $submittedSlots = $request->input('slots');

        if (is_array($submittedSlots) && count($submittedSlots) > 0) {
            $firstSlot = reset($submittedSlots);

            if (is_array($firstSlot)) {
                $request->merge([
                    'reservation_date' => $request->input(
                        'reservation_date',
                        $firstSlot['reservation_date'] ?? null
                    ),
                    'start_time' => $request->input(
                        'start_time',
                        $firstSlot['start_time'] ?? null
                    ),
                    'end_time' => $request->input(
                        'end_time',
                        $firstSlot['end_time'] ?? null
                    ),
                ]);
            }
        }

        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'class_name' => ['required', 'string', 'max:100'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slots' => ['sometimes', 'array', 'min:1'],
            'slots.*.reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
            'slots.*.period_no' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $slots = $validated['slots'] ?? [[
            'reservation_date' => $validated['reservation_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'period_no' => null,
        ]];

        $uniqueSlots = [];

        foreach ($slots as $slot) {
            if (
                strtotime($slot['end_time']) <= strtotime($slot['start_time'])
            ) {
                return back()
                    ->withInput()
                    ->with('error', 'Each time slot must end after it starts.');
            }

            $key = implode('|', [
                $slot['reservation_date'],
                $slot['start_time'],
                $slot['end_time'],
            ]);

            if (isset($uniqueSlots[$key])) {
                return back()
                    ->withInput()
                    ->with('error', 'Duplicate time slots were selected. Please review your selection.');
            }

            $uniqueSlots[$key] = $slot;
        }

        $slots = array_values($uniqueSlots);
        $firstSlot = $slots[0];

        $result = DB::transaction(function () use (
            $validated,
            $slots,
            $firstSlot,
            $request
        ) {
            $classroom = Classroom::query()
                ->whereKey($validated['classroom_id'])
                ->lockForUpdate()
                ->first();

            if (
                ! $classroom ||
                ! $classroom->is_active ||
                $classroom->reservation_status !== 'normal'
            ) {
                return ['type' => 'unavailable'];
            }


            foreach ($slots as $slot) {
                $date = $slot['reservation_date'];
                $start = $slot['start_time'];
                $end = $slot['end_time'];

                $approvedSlotConflict = DB::table('classroom_reservation_slots as slots')
                    ->join(
                        'classroom_reservations as reservations',
                        'reservations.id',
                        '=',
                        'slots.classroom_reservation_id'
                    )
                    ->where('reservations.classroom_id', $classroom->id)
                    ->where('reservations.status', 'approved')
                    ->whereDate('slots.reservation_date', $date)
                    ->where('slots.start_time', '<', $end)
                    ->where('slots.end_time', '>', $start)
                    ->select([
                        'reservations.id',
                        'reservations.class_name',
                        'slots.reservation_date',
                        'slots.start_time',
                        'slots.end_time',
                    ])
                    ->first();

                $approvedLegacyConflict = ClassroomReservation::query()
                    ->where('classroom_id', $classroom->id)
                    ->where('status', 'approved')
                    ->whereDate('reservation_date', $date)
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start)
                    ->whereDoesntHave('slots')
                    ->first();

                if ($approvedSlotConflict || $approvedLegacyConflict) {
                    $conflict = $approvedSlotConflict ?? $approvedLegacyConflict;

                    return [
                        'type' => 'conflict',
                        'reservation' => $conflict,
                        'date' => $date,
                    ];
                }

            }


            $reservation = ClassroomReservation::create([
                'classroom_id' => $classroom->id,
                'requester_id' => $request->user()->id,
                'class_name' => $validated['class_name'],
                'reservation_date' => $firstSlot['reservation_date'],
                'start_time' => $firstSlot['start_time'],
                'end_time' => $firstSlot['end_time'],
                'status' => 'pending',
                'purpose' => $validated['purpose'] ?? null,
            ]);

            foreach ($slots as $slot) {
                $reservation->slots()->create([
                    'reservation_date' => $slot['reservation_date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'period_no' => $slot['period_no'] ?? null,
                ]);
            }

            AuditLogger::log(
                'created',
                $reservation,
                [
                    'classroom_id' => $classroom->id,
                    'class_name' => $reservation->class_name,
                    'slots' => $slots,
                    'status' => $reservation->status,
                ],
                'Reservation request created: ' . $reservation->class_name
            );

            return ['type' => 'created'];
        });

        if ($result['type'] === 'unavailable') {
            return back()
                ->withInput()
                ->with('error', 'The classroom is unavailable or is not accepting reservations.');
        }

        if ($result['type'] === 'conflict') {
            $conflict = $result['reservation'];

            return back()
                ->withInput()
                ->with(
                    'error',
                    'The selected time on ' . $result['date'] .
                    ' conflicts with an approved reservation for ' .
                    $conflict->class_name . ' (' .
                    substr((string) $conflict->start_time, 0, 5) . '-' .
                    substr((string) $conflict->end_time, 0, 5) .
                    '). Please choose another time.'
                );
        }


        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reservation request submitted. Please wait for approval.');
    }
    public function approve(Request $request, ClassroomReservation $reservation)
    {
        abort_unless(
            $request->user()->hasPermission('reservations.manage'),
            403
        );

        $result = DB::transaction(function () use ($reservation, $request) {
            // 先鎖定預約資料，避免兩位管理人員同時審核。
            $lockedReservation = ClassroomReservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservation->status !== 'pending') {
                return ['type' => 'already_reviewed'];
            }

            // 鎖定教室，並重新檢查教室是否仍可外借。
            $classroom = Classroom::query()
                ->whereKey($lockedReservation->classroom_id)
                ->lockForUpdate()
                ->first();

            if (
                ! $classroom ||
                ! $classroom->is_active ||
                $classroom->reservation_status !== 'normal'
            ) {
                return ['type' => 'unavailable'];
            }

            // 核准前逐一檢查所有時段，避免跨日期預約漏掉衝突。
            $candidateSlots = $lockedReservation->slots
                ->map(function ($slot) {
                    return [
                        'reservation_date' => $slot->reservation_date->format('Y-m-d'),
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                    ];
                })
                ->all();

            // 舊資料沒有子時段時，仍以主預約欄位檢查。
            if (empty($candidateSlots)) {
                $candidateSlots = [[
                    'reservation_date' => $lockedReservation->reservation_date->format('Y-m-d'),
                    'start_time' => $lockedReservation->start_time,
                    'end_time' => $lockedReservation->end_time,
                ]];
            }

            $conflict = false;

            foreach ($candidateSlots as $slot) {
                $date = $slot['reservation_date'];
                $start = $slot['start_time'];
                $end = $slot['end_time'];

                // 檢查其他已核准預約的子時段。
                $slotConflict = DB::table('classroom_reservation_slots as slots')
                    ->join(
                        'classroom_reservations as reservations',
                        'reservations.id',
                        '=',
                        'slots.classroom_reservation_id'
                    )
                    ->where('reservations.classroom_id', $lockedReservation->classroom_id)
                    ->where('reservations.status', 'approved')
                    ->where('reservations.id', '<>', $lockedReservation->id)
                    ->whereDate('slots.reservation_date', $date)
                    ->where('slots.start_time', '<', $end)
                    ->where('slots.end_time', '>', $start)
                    ->exists();

                // 同時檢查沒有子時段的舊格式預約。
                $legacyConflict = ClassroomReservation::query()
                    ->where('classroom_id', $lockedReservation->classroom_id)
                    ->whereDate('reservation_date', $date)
                    ->whereKeyNot($lockedReservation->id)
                    ->where('status', 'approved')
                    ->whereDoesntHave('slots')
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start)
                    ->exists();

                if ($slotConflict || $legacyConflict) {
                    $conflict = true;
                    break;
                }
            }

            if ($conflict) {
                return ['type' => 'conflict'];
            }
            $lockedReservation->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLogger::log(
                'status_changed',
                $lockedReservation,
                [
                    'status' => [
                        'old' => 'pending',
                        'new' => 'approved',
                    ],
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $lockedReservation->reviewed_at->toDateTimeString(),
                ],
                '核准空間預約：' . $lockedReservation->class_name
            );

            return ['type' => 'approved'];
        });

        if ($result['type'] === 'already_reviewed') {
            return redirect()
                ->route('reservations.index')
                ->with('error', '這筆預約已經審核過，無法重複操作。');
        }

        if ($result['type'] === 'unavailable') {
            return redirect()
                ->route('reservations.index')
                ->with('error', '教室目前已停用或暫停外借，無法核准此預約。');
        }

        if ($result['type'] === 'conflict') {
            return redirect()
                ->route('reservations.index')
                ->with('error', '核准失敗：此時段已有其他已核准的預約。');
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', '預約已核准。');
    }

    /**
     * 拒絕空間預約，並要求填寫原因。
     */
    public function reject(Request $request, ClassroomReservation $reservation)
    {
        abort_unless(
            $request->user()->hasPermission('reservations.manage'),
            403
        );

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => '請填寫拒絕預約的原因。',
            'rejection_reason.max' => '拒絕原因不可超過 500 個字元。',
        ]);

        $result = DB::transaction(function () use ($reservation, $request, $validated) {
            $lockedReservation = ClassroomReservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservation->status !== 'pending') {
                return ['type' => 'already_reviewed'];
            }

            $lockedReservation->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            AuditLogger::log(
                'status_changed',
                $lockedReservation,
                [
                    'status' => [
                        'old' => 'pending',
                        'new' => 'rejected',
                    ],
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $lockedReservation->reviewed_at->toDateTimeString(),
                    'rejection_reason' => $lockedReservation->rejection_reason,
                ],
                '拒絕空間預約：' . $lockedReservation->class_name
            );

            return ['type' => 'rejected'];
        });

        if ($result['type'] === 'already_reviewed') {
            return redirect()
                ->route('reservations.index')
                ->with('error', '這筆預約已經審核過，無法重複操作。');
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', '預約已拒絕。');
    }
}
