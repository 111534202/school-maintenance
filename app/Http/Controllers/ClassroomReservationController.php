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
     * 空間申請（預約列表，只有預約管理權限的人看得到）：只列送出時間、申請人、申請班級、申請狀態（已審核／未審核），
     * 點一筆在畫面中的對話框打開「預約詳細」（可直接核准／拒絕）。
     * 一般使用者只看自己的申請；有預約管理權限的人可以查看全部申請。
     */
    public function index(Request $request, ReservationReviewService $reviewService)
    {
        // 空間申請只給有預約管理（審核）權限的人；一般使用者在「空間預約」頁下方的「我的申請」看自己的申請。
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        // 時間已到、或和已核准時段重疊的待審核申請先自動拒絕，列表上的狀態才會正確（排程沒開也一樣）。
        $reviewService->sweep();

        $query = ClassroomReservation::with(['requester'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")   // 未審核的排前面
            ->orderByDesc('created_at');

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
     * 有預約管理權限的人可以在這裡整筆核准或整筆拒絕。申請人本人也可以看自己的申請。
     */
    public function show(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        $canManage = $request->user()->hasPermission('reservations.manage');
        abort_unless($canManage || $reservation->requester_id === $request->user()->id, 403);

        $reviewService->sweep();
        $reservation->refresh()->load(['classroom', 'requester', 'reviewer', 'slots']);

        // 審核參考：核准後會被自動拒絕的其他待審核申請（和已核准時段重疊的申請在上面已經自動拒絕了）。
        $willAutoReject = $canManage && ! $reservation->isReviewed()
            ? $reviewService->overlappingPending($reservation)
            : [];

        // 教室目前在等待維修的設備（審核時參考）；暫停預約時不能核准。
        $repairNotices = ClassroomRepairNotices::for([$reservation->classroom_id])[$reservation->classroom_id] ?? [];
        $classroomSuspended = ClassroomRepairNotices::isSuspended((int) $reservation->classroom_id);

        return view('reservations.show', compact('reservation', 'canManage', 'repairNotices', 'classroomSuspended', 'willAutoReject'));
    }

    /**
     * 顯示新增預約表單。
     * 所有啟用中的教室都可以選、可以查詢課表；暫停預約的教室（核心設備等待維修）不能送出申請。
     */
    public function create(Request $request, ReservationReviewService $reviewService)
    {
        // 下方「我的申請」：自己提出的申請與目前狀態（先跑自動拒絕，狀態才正確）；點一筆在對話框看預約詳細。
        $reviewService->sweep();
        $myReservations = ClassroomReservation::query()
            ->with('classroom')
            ->where('requester_id', $request->user()->id)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'my_page')
            ->withQueryString()
            ->fragment('my-requests');

        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // 每間教室目前在等待維修的設備，以及哪些教室暫停預約；選好教室後在畫面上提醒。
        $repairNotices = ClassroomRepairNotices::for($classrooms->pluck('id'));
        $suspendedIds = ClassroomRepairNotices::suspendedIds($classrooms->pluck('id'));

        return view('reservations.create', compact('classrooms', 'repairNotices', 'suspendedIds', 'myReservations'));
    }

    /**
     * 儲存預約申請，並檢查是否與既有申請撞期。
     */
    public function store(Request $request)
    {
        $submittedSlots = $request->input('slots');

        // 表單上的日期／開始／結束欄位只用來驗證與「沒有選時段時當成一個時段」，不會存進申請本身
        // （使用時間只存在時段明細）。一次選多個時段（可跨日）時，拿最早的那一個時段來驗證，
        // 不能拿第一個時段的開始配最後一個時段的結束，否則跨日選取會被誤判成「結束時間早於開始時間」。
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
            'slots.*.period_number' => ['nullable', 'integer', 'min:0'],   // 早自習為 0、午休為空
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $slots = $validated['slots'] ?? [[
            'reservation_date' => $validated['reservation_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'period_number' => null,
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

        $result = DB::transaction(function () use (
            $validated,
            $slots,
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
                'status' => 'pending',
                'purpose' => $validated['purpose'] ?? null,
            ]);

            foreach ($slots as $slot) {
                $reservation->slots()->create([
                    'reservation_date' => $slot['reservation_date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'period_number' => $slot['period_number'] ?? null,
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
            ->to(route('reservations.create') . '#my-requests')
            ->with('success', '已送出預約申請，請等待審核。可以在下方「我的申請」查看進度。');
    }
    /** 整筆核准（每個時段都同意）。 */
    public function approve(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        return $this->afterReview($reviewService->approveAll($reservation, $request->user()));
    }

    /** 整筆拒絕（每個時段都拒絕）；拒絕原因選填。 */
    public function reject(Request $request, ClassroomReservation $reservation, ReservationReviewService $reviewService)
    {
        abort_unless($request->user()->hasPermission('reservations.manage'), 403);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'rejection_reason.max' => '拒絕原因不可超過 500 個字元。',
        ]);

        return $this->afterReview(
            $reviewService->rejectAll($reservation, $request->user(), $validated['rejection_reason'] ?? null)
        );
    }

    /**
     * 審核完成後回到空間申請列表並顯示訊息。
     * 從對話框（?modal=1）送出時，EmbeddedModal 會關掉對話框、讓列表頁重新整理。
     */
    private function afterReview(array $result)
    {
        $redirect = redirect()->route('reservations.index');

        return match ($result['type']) {
            'already_reviewed' => $redirect->with('error', '這筆預約已經審核過，無法重複操作。'),
            'expired' => $redirect->with('error', '這筆申請的時段已經開始，系統已自動拒絕，無法再核准。'),
            'unavailable' => $redirect->with('error', '教室目前已停用，或核心設備正在維修而暫停預約，無法核准此預約（仍可拒絕）。'),
            'conflict' => $redirect->with('error', '無法核准：下列時段已核准給其他申請，這筆申請已自動拒絕：' . implode('、', $result['slots'])),
            default => $redirect->with('success', $result['status'] === ClassroomReservation::STATUS_APPROVED
                ? '預約已核准。' . (($result['auto_rejected'] ?? 0) > 0 ? "時段重疊的 {$result['auto_rejected']} 筆待審核申請已自動拒絕。" : '')
                : '預約已拒絕。'),
        };
    }
}
