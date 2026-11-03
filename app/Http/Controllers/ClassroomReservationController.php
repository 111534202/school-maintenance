<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Services\AuditLogger;
use App\Services\ClassroomRepairNotices;
use App\Services\ReservationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassroomReservationController extends Controller
{
    /**
     * 預約列表：只列申請時間、申請人、申請班級、申請狀態（已審核／未審核），點一行進入詳細頁。
     * 一般使用者只看自己的申請；有預約管理權限的人可以查看全部申請。
     */
    public function index(Request $request)
    {
        $query = ClassroomReservation::with(['requester'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")   // 未審核的排前面
            ->orderByDesc('created_at');

        if (! $request->user()->hasPermission('reservations.manage')) {
            $query->where('requester_id', $request->user()->id);
        }

        // 列表上方三張統計卡：全部／未審核／已審核（範圍和列表相同，篩選前計算）。
        $counts = [
            'all' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', ClassroomReservation::STATUS_PENDING)->count(),
        ];
        $counts['reviewed'] = $counts['all'] - $counts['pending'];

        // 申請狀態篩選：pending＝未審核（主控台「空間預約待審核」卡片連過來）；reviewed＝已審核。
        $status = $request->query('status');
        if ($status === 'pending') {
            $query->where('status', ClassroomReservation::STATUS_PENDING);
        } elseif ($status === 'reviewed') {
            $query->where('status', '<>', ClassroomReservation::STATUS_PENDING);
        }

        $reservations = $query->paginate(15)->withQueryString();

        return view('reservations.index', compact('reservations', 'counts'));
    }

    /**
     * 預約詳細頁：教室、預約日期、使用時段、用途、審核人與審核時間；
     * 有預約管理權限的人可以在這裡逐一審核每個時段。申請人本人也可以看自己的申請。
     */
    public function show(Request $request, ClassroomReservation $reservation)
    {
        $canManage = $request->user()->hasPermission('reservations.manage');
        abort_unless($canManage || $reservation->requester_id === $request->user()->id, 403);

        // 舊資料沒有時段明細：用整筆申請的時間補一個時段，審核畫面才能逐一選擇。
        if (! $reservation->slots()->exists()) {
            $reservation->slots()->create([
                'reservation_date' => $reservation->reservation_date->toDateString(),
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
                'period_no' => null,
            ]);
        }

        $reservation->load(['classroom', 'requester', 'reviewer', 'slots']);

        // 教室目前在等待維修的設備（審核時參考）；暫停預約時不能核准。
        $repairNotices = ClassroomRepairNotices::for([$reservation->classroom_id])[$reservation->classroom_id] ?? [];
        $classroomSuspended = ClassroomRepairNotices::isSuspended((int) $reservation->classroom_id);

        return view('reservations.show', compact('reservation', 'canManage', 'repairNotices', 'classroomSuspended'));
    }

    /**
     * 分時段審核：每個時段都要選「同意」或「拒絕」，拒絕要寫原因；同意的時段要再確認沒有和其他已核准的時段衝突。
     */
    public function review(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        $validated = $request->validate([
            'decisions' => ['required', 'array'],
            'decisions.*.decision' => ['required', 'in:approved,rejected'],
            'decisions.*.reason' => ['nullable', 'required_if:decisions.*.decision,rejected', 'string', 'max:500'],
        ], [
            'decisions.required' => '請逐一選擇每個時段要同意或拒絕。',
            'decisions.*.decision.required' => '每個時段都要選擇同意或拒絕。',
            'decisions.*.reason.required_if' => '拒絕的時段請填寫原因。',
            'decisions.*.reason.max' => '拒絕原因不可超過 500 個字元。',
        ]);

        $result = $reviewService->review($reservation, $validated['decisions'], $request->user());

        return $this->afterReview($result, route('reservations.show', $reservation));
    }

    /**
     * 顯示新增預約表單。
     * 所有啟用中的教室都可以選、可以查詢課表；暫停預約的教室（核心設備等待維修）不能送出申請。
     */
    public function create()
    {
        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // 每間教室目前在等待維修的設備，以及哪些教室暫停預約；選好教室後在畫面上提醒。
        $repairNotices = ClassroomRepairNotices::for($classrooms->pluck('id'));
        $suspendedIds = ClassroomRepairNotices::suspendedIds($classrooms->pluck('id'));

        return view('reservations.create', compact('classrooms', 'repairNotices', 'suspendedIds'));
    }

    /**
     * 儲存預約申請，並檢查是否與既有申請撞期。
     */
    public function store(Request $request)
    {
        $submittedSlots = $request->input('slots');

        // 一次選多個時段（可跨日）時，申請本身的日期／開始／結束只是「代表時段」：
        // 一律取最早的那一個時段（同一天內的開始與結束），不能拿第一個時段的開始配最後一個時段的結束，
        // 否則跨日選取（例如 11/17 下午＋11/18 第一節）會被誤判成「結束時間早於開始時間」。
        if (is_array($submittedSlots) && count($submittedSlots) > 0) {
            $validSlots = array_values(array_filter($submittedSlots, fn ($slot) => is_array($slot)
                && is_string($slot['reservation_date'] ?? null)
                && is_string($slot['start_time'] ?? null)));

            if ($validSlots !== []) {
                usort($validSlots, fn ($a, $b) => [$a['reservation_date'], $a['start_time']] <=> [$b['reservation_date'], $b['start_time']]);
                $firstSlot = $validSlots[0];

                $request->merge([
                    'reservation_date' => $firstSlot['reservation_date'],
                    'start_time' => $firstSlot['start_time'],
                    'end_time' => $firstSlot['end_time'] ?? null,
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
            'slots.*.period_no' => ['nullable', 'integer', 'min:0'],   // 早自習為 0、午休為空
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
            // 不能預約已經開始（或已經過去）的時段：今天的節次，開始時間必須晚於現在。
            if (
                $slot['reservation_date'] === now()->toDateString()
                && substr((string) $slot['start_time'], 0, 5) <= now()->format('H:i')
            ) {
                return back()
                    ->withInput()
                    ->with('error', '不能預約已經過去的時段（' . $slot['reservation_date'] . ' ' . substr((string) $slot['start_time'], 0, 5) . '），請重新選擇。');
            }

            if (
                strtotime($slot['end_time']) <= strtotime($slot['start_time'])
            ) {
                return back()
                    ->withInput()
                    ->with('error', '每個時段的結束時間都必須晚於開始時間。');
            }

            $key = implode('|', [
                $slot['reservation_date'],
                $slot['start_time'],
                $slot['end_time'],
            ]);

            if (isset($uniqueSlots[$key])) {
                return back()
                    ->withInput()
                    ->with('error', '選到重複的時段，請重新確認。');
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

            if (! $classroom || ! $classroom->is_active) {
                return ['type' => 'unavailable'];
            }

            // 教室被標成設備異常（核心設備維修處理中／停用）：暫停預約，不能送出。
            if (ClassroomRepairNotices::isSuspended($classroom->id)) {
                return ['type' => 'suspended'];
            }


            foreach ($slots as $slot) {
                $date = $slot['reservation_date'];
                $start = $slot['start_time'];
                $end = $slot['end_time'];

                // 和其他「已核准」的時段重疊就不能送出（包含部分核准申請裡已同意的時段）。
                $conflict = app(ReservationReviewService::class)->approvedConflict($classroom->id, $date, $start, $end);

                if ($conflict) {
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
                ->with('error', '這間教室目前已停用，無法預約。');
        }

        if ($result['type'] === 'suspended') {
            return back()
                ->withInput()
                ->with('error', '這間教室的核心設備正在維修，目前暫停預約，無法送出申請。');
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
    /** 整筆核准（每個時段都同意）。 */
    public function approve(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        return $this->afterReview($reviewService->approveAll($reservation, $request->user()), route('reservations.index'));
    }

    /** 整筆拒絕（每個時段都拒絕），並要求填寫原因。 */
    public function reject(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => '請填寫拒絕預約的原因。',
            'rejection_reason.max' => '拒絕原因不可超過 500 個字元。',
        ]);

        return $this->afterReview(
            $reviewService->rejectAll($reservation, $request->user(), $validated['rejection_reason']),
            route('reservations.index')
        );
    }

    /** 審核完成後依結果導向並顯示訊息。 */
    private function afterReview(array $result, string $redirectTo)
    {
        $redirect = redirect()->to($redirectTo);

        return match ($result['type']) {
            'already_reviewed' => $redirect->with('error', '這筆預約已經審核過，無法重複操作。'),
            'unavailable' => $redirect->with('error', '教室目前已停用，或核心設備正在維修而暫停預約，無法核准此預約（仍可拒絕）。'),
            'conflict' => $redirect->with('error', '核准失敗：下列時段已有其他已核准的預約，請改為拒絕或調整後再送出：' . implode('、', $result['slots'])),
            'incomplete' => $redirect->with('error', ($result['missing_reason'] ?? false)
                ? "時段 {$result['slot']} 選擇拒絕，請填寫原因。"
                : "時段 {$result['slot']} 還沒有選擇同意或拒絕，每個時段都要決定才能送出。"),
            default => $redirect->with('success', match ($result['status']) {
                ClassroomReservation::STATUS_APPROVED => '預約已核准。',
                ClassroomReservation::STATUS_REJECTED => '預約已拒絕。',
                default => "預約已部分核准：同意 {$result['approved']} 個時段、拒絕 {$result['rejected']} 個時段。",
            }),
        };
    }
}
