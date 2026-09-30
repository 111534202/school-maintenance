<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Models\Device;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\User;
use App\Http\Requests\AssignRepairRequestRequest;
use App\Http\Requests\RejectRepairRequestRequest;
use App\Http\Requests\StoreRepairRequestRequest;
use App\Services\AttachmentUploader;
use App\Services\RepairAssignmentNotifier;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 報修單的網頁功能都在這支 Controller。每個 public 方法對應一個網址（見
 * routes/web.php），負責「接收使用者的請求 → 呼叫 Model/Service 做事 →
 * 回傳網頁或跳轉」，實際的規則（例如狀態能不能轉換）都不寫在這裡，而是丟給
 * App\Services\RepairRequestWorkflow 處理，這支 Controller 只負責「串接」。
 */
class RepairRequestController extends Controller
{
    /**
     * 維修案件看板（依《第 2 週個人工作計畫》第 2 項，第三週補強看板資訊）。
     *
     * 可以用網址參數 ?status=xxx 依狀態篩選、?location=xxx 依教室/地點篩選
     * （模糊比對，例如打 "A1" 也找得到 "A101"）、?assignee=xxx 依維修人員篩選
     * （依《第四週個人工作計畫》第 1 項新增，一樣是模糊比對）。
     *
     * 另外會算出「每個維修人員手上還有幾張未結案的案件」，讓主管可以看客觀
     * 資料自己判斷要不要多分派給某人（規格明確要求「不做自動派工推薦」，
     * 所以這裡只顯示數字，不會自動幫忙排序或指定人選）。
     */
    public function index(Request $request)
    {
        $repairRequests = RepairRequest::query()
            ->with(['device', 'assignedTechnician'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('location'), fn ($query) => $query->where('location', 'like', '%' . $request->string('location') . '%'))
            // 維修人員篩選：users 表合併後改成對「已指派的真實使用者姓名」模糊比對，
            // 沒有 assigned_to（例如保養 NG 轉報修等舊資料）就退回比對 assignee_note 文字。
            ->when($request->filled('assignee'), function ($query) use ($request) {
                $keyword = $request->string('assignee');
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('assignedTechnician', fn ($uq) => $uq->where('name', 'like', "%{$keyword}%"))
                        ->orWhere('assignee_note', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $statuses = RepairRequestStatus::cases();

        // 統計「每個維修人員手上還有幾張還沒結案的案件」，現在以真正的 assigned_to
        // （users.id）分組，比用文字 assignee_note 分組更準確（不會被同名不同人混淆）。
        $activeCaseCountsByAssignee = RepairRequest::query()
            ->whereNotNull('assigned_to')
            ->where('status', '!=', RepairRequestStatus::Completed->value)
            ->selectRaw('assigned_to, count(*) as active_count')
            ->groupBy('assigned_to')
            ->pluck('active_count', 'assigned_to');

        return view('repairs.index', compact('repairRequests', 'statuses', 'activeCaseCountsByAssignee'));
    }

    /**
     * 顯示「新增報修」表單。
     *
     * 支援兩種簡化流程帶入資料：
     * - from_kb：從 Knowledge Base 頁面「無法排除，前往報修」連結過來，帶入描述提示。
     * - device：掃描設備條碼／QR（林政寬的 devices.entry 設備入口頁「前往報修」按鈕，
     *   或直接在表單頁用條碼輸入框查詢）過來，自動帶入設備名稱、型號、所在教室等
     *   資訊，使用者不用再自己打字描述設備，達到「簡化報修流程」的目的。
     */
    public function create()
    {
        $fromKnowledgeBase = null;
        $device = null;

        if (request()->filled('from_kb')) {
            $fromKnowledgeBase = KnowledgeBase::find(request()->integer('from_kb'));
        }

        if (request()->filled('device')) {
            $device = Device::with(['category', 'classroom'])->find(request()->integer('device'));
        }

        return view('repairs.create', compact('fromKnowledgeBase', 'device'));
    }

    /**
     * 設備條碼／QR 掃描查詢（給新增報修頁的「掃描設備條碼」欄位用 AJAX 查詢），
     * 掃描或輸入 device_code 後直接回傳 JSON，前端 JS 收到後即時把設備資訊填進
     * 表單欄位，不用整頁重新導向，是「簡化報修流程」的核心功能。
     */
    public function deviceLookup(Device $device)
    {
        $device->load(['category', 'classroom']);

        return response()->json([
            'id' => $device->id,
            'device_code' => $device->device_code,
            'display' => trim(
                ($device->category->name ?? '') . ' ' . $device->brand . ' ' . $device->model
                . '（' . ($device->classroom->room_name ?? $device->classroom->room_code ?? '') . '）'
            ),
            'title_suggestion' => trim(($device->classroom->room_code ?? '') . ' ' . ($device->category->name ?? '') . '故障'),
        ]);
    }

    /**
     * 使用者送出「新增報修」表單後，真正把資料存進資料庫。
     * 附件（例如故障照片）如果有上傳，會另外存進 attachments 表；reporter_id
     * 現在直接用登入者的 id（auth 中介層保證一定有登入者，不會是 null）。
     */
    public function store(StoreRepairRequestRequest $request, AttachmentUploader $attachmentUploader)
    {
        $repairRequest = RepairRequest::create([
            ...$request->safe()->except('attachments'),
            'reporter_id' => Auth::id(),
        ]);

        if ($request->hasFile('attachments')) {
            $attachmentUploader->storeMany($repairRequest, $request->file('attachments'));
        }

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.submitted'));
    }

    /**
     * 報修單詳細頁：顯示這張報修單的所有資訊、附件，以及底下的維修紀錄。
     */
    public function show(RepairRequest $repairRequest)
    {
        $repairRequest->load(['attachments', 'repairLogs.attachments', 'device.category', 'device.classroom', 'assignedTechnician']);

        // 派工／重新指派表單要挑選真正的維修人員（角色 = technician），
        // 不再讓主管自己打字輸入姓名。
        $technicians = User::whereHas('role', fn ($q) => $q->where('slug', 'technician'))
            ->orderBy('name')
            ->get();

        return view('repairs.show', compact('repairRequest', 'technicians'));
    }

    /**
     * 人工派工（依《第 2 週個人工作計畫》第 3 項）：新報修 -> 已派工。
     * 實際的狀態檢查與寫入邏輯都在 RepairRequestWorkflow::assign()，
     * 這裡只負責接收表單資料、呼叫它、處理成功/失敗後要跳轉去哪裡。
     */
    public function assign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow, RepairAssignmentNotifier $notifier)
    {
        $technician = User::findOrFail($request->validated('assigned_to'));

        try {
            $workflow->assign(
                $repairRequest,
                $technician->name,
                $request->validated('scheduled_at'),
                $technician->id,
            );
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        $notifier->notify($repairRequest, $technician);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.dispatched'));
    }

    /**
     * 重新指派（依《第四週個人工作計畫》第 1 項）：案件已經派過工了，但主管想
     * 換一個維修人員或改一下處理日期，不需要重新走一次狀態轉換。
     * 跟 assign() 共用同一份表單驗證規則（欄位一模一樣），只是呼叫的 workflow
     * 方法不同：assign() 會把狀態從「新報修」轉成「已派工」，reassign() 只換人。
     */
    public function reassign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow, RepairAssignmentNotifier $notifier)
    {
        $technician = User::findOrFail($request->validated('assigned_to'));

        try {
            $workflow->reassign(
                $repairRequest,
                $technician->name,
                $request->validated('scheduled_at'),
                $technician->id,
            );
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        $notifier->notify($repairRequest, $technician);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.reassigned'));
    }

    /**
     * 維修人員開始處理：已派工 -> 處理中。
     */
    public function start(RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        try {
            $workflow->start($repairRequest);
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.started'));
    }

    /**
     * 報修人驗收通過，結案：待驗收 -> 已結案（依《第三週個人工作計畫》第 2 項）。
     * 已結案之後，這張報修單就不能再變動狀態了。
     */
    public function complete(RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        try {
            $workflow->complete($repairRequest);
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.completed'));
    }

    /**
     * 報修人驗收不通過，退回重新處理：待驗收 -> 處理中，並記錄退回原因
     * （依《第三週個人工作計畫》第 3 項「驗收退回」）。
     * 之前的維修紀錄不會被刪除，維修人員之後會再填一筆新的維修紀錄。
     */
    public function reject(RejectRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        try {
            $workflow->reject($repairRequest, $request->validated('rejection_reason'));
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.rejected'));
    }

    /**
     * 狀態轉換不合法時（例如使用者連點兩下、開多個分頁各按一次按鈕）會用到這個。
     * 導回詳細頁顯示友善的錯誤訊息，不讓使用者看到 Laravel 預設的 500 錯誤頁。
     */
    private function redirectWithWorkflowError(RepairRequest $repairRequest, DomainException $exception)
    {
        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('error', $exception->getMessage());
    }
}
