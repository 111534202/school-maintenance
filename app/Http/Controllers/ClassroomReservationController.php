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
        $query = ClassroomReservation::with(['classroom', 'user'])
            ->orderByDesc('reservation_date')
            ->orderBy('start_time');

        if (! $request->user()->hasPermission('reservations.manage')) {
            $query->where('user_id', $request->user()->id);
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
            ->orderBy('room_name')
            ->get();

        return view('reservations.create', compact('classrooms'));
    }

    /**
     * 儲存預約申請，並檢查是否與既有申請撞期。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => [
                'required',
                'integer',
                'exists:classrooms,id',
            ],
            'class_name' => [
                'required',
                'string',
                'max:100',
            ],
            'reservation_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'purpose' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'class_name.required' => '請輸入班級名稱。',
            'class_name.max' => '班級名稱不可超過 100 個字元。',
            'reservation_date.after_or_equal' => '預約日期不可早於今天。',
            'end_time.after' => '結束時間必須晚於開始時間。',
        ]);

        // 鎖定教室資料列，讓同一間教室的同時送件依序檢查。
        $result = DB::transaction(function () use ($validated, $request) {
            $classroom = Classroom::query()
                ->whereKey($validated['classroom_id'])
                ->lockForUpdate()
                ->first();

            // 教室可能已被停用，或已因設備異常而暫停外借。
            if (
                ! $classroom ||
                ! $classroom->is_active ||
                $classroom->reservation_status !== 'normal'
            ) {
                return ['type' => 'unavailable'];
            }

            // 待審核及已核准的預約都會佔用時段。
            // 開始時間 < 對方結束時間，且結束時間 > 對方開始時間，
            // 才算時間重疊；首尾相接不算衝突。
            $conflict = ClassroomReservation::query()
                ->where('classroom_id', $classroom->id)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereIn('status', ['pending', 'approved'])
                ->where('start_time', '<', $validated['end_time'])
                ->where('end_time', '>', $validated['start_time'])
                ->first();

            if ($conflict) {
                return [
                    'type' => 'conflict',
                    'reservation' => $conflict,
                ];
            }

            $reservation = ClassroomReservation::create([
                'classroom_id' => $classroom->id,
                'user_id' => $request->user()->id,
                'class_name' => $validated['class_name'],
                'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'status' => 'pending',
                'purpose' => $validated['purpose'] ?? null,
            ]);

            AuditLogger::log(
                'created',
                $reservation,
                [
                    'classroom_id' => $classroom->id,
                    'class_name' => $reservation->class_name,
                    'reservation_date' => $reservation->reservation_date->toDateString(),
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'status' => $reservation->status,
                ],
                '建立空間預約申請：' . $reservation->class_name
            );

            return ['type' => 'created'];
        });

        if ($result['type'] === 'unavailable') {
            return back()
                ->withInput()
                ->with('error', '這間教室目前已停用或暫停外借，請重新選擇教室。');
        }

        if ($result['type'] === 'conflict') {
            $conflict = $result['reservation'];

            $statusLabel = $conflict->status === 'pending'
                ? '尚待審核'
                : '已核准';

            return back()
                ->withInput()
                ->with(
                    'error',
                    '目前已有「' . $conflict->class_name . '」班級申請此時段（' .
                    $statusLabel . '），建議重新選擇時間。'
                );
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', '預約申請已送出，請等待管理人員審核。');
    }
    /**
     * 核准空間預約。
     */
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

            // 核准前再次檢查，避免重疊的預約同時獲准。
            $conflict = ClassroomReservation::query()
                ->where('classroom_id', $lockedReservation->classroom_id)
                ->whereDate('reservation_date', $lockedReservation->reservation_date)
                ->whereKeyNot($lockedReservation->id)
                ->where('status', 'approved')
                ->where('start_time', '<', $lockedReservation->end_time)
                ->where('end_time', '>', $lockedReservation->start_time)
                ->exists();

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
