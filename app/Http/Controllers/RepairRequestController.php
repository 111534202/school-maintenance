<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Http\Requests\AssignRepairRequestRequest;
use App\Http\Requests\RejectRepairRequestRequest;
use App\Http\Requests\StoreRepairRequestRequest;
use App\Services\AttachmentUploader;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Http\Request;

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
     * （模糊比對，例如打 "A1" 也找得到 "A101"）。
     *
     * 另外會算出「每個維修人員手上還有幾張未結案的案件」，讓主管可以看客觀
     * 資料自己判斷要不要多分派給某人（規格明確要求「不做自動派工推薦」，
     * 所以這裡只顯示數字，不會自動幫忙排序或指定人選）。
     */
    public function index(Request $request)
    {
        $repairRequests = RepairRequest::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('location'), fn ($query) => $query->where('location', 'like', '%' . $request->string('location') . '%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $statuses = RepairRequestStatus::cases();

        // 統計「每個維修人員（用 assignee_note 這個文字欄位代表）手上還有幾張
        // 還沒結案的案件」。因為 users 表還沒合併，這裡只能用文字分組，
        // 等真正的 users 表接上後可以改成用 assigned_to 這個 id 分組。
        $activeCaseCountsByAssignee = RepairRequest::query()
            ->whereNotNull('assignee_note')
            ->where('status', '!=', RepairRequestStatus::Completed->value)
            ->selectRaw('assignee_note, count(*) as active_count')
            ->groupBy('assignee_note')
            ->pluck('active_count', 'assignee_note');

        return view('repair-requests.index', compact('repairRequests', 'statuses', 'activeCaseCountsByAssignee'));
    }

    /**
     * 顯示「新增報修」表單。
     *
     * 支援從 Knowledge Base 頁面「無法排除，前往報修」連結帶 from_kb 參數過來，
     * 只做最小導流（帶入描述提示），不做複雜故障類型推薦。
     */
    public function create()
    {
        $fromKnowledgeBase = null;

        if (request()->filled('from_kb')) {
            $fromKnowledgeBase = KnowledgeBase::find(request()->integer('from_kb'));
        }

        return view('repair-requests.create', compact('fromKnowledgeBase'));
    }

    /**
     * 使用者送出「新增報修」表單後，真正把資料存進資料庫。
     * 附件（例如故障照片）如果有上傳，會另外存進 attachments 表。
     */
    public function store(StoreRepairRequestRequest $request, AttachmentUploader $attachmentUploader)
    {
        $repairRequest = RepairRequest::create($request->safe()->except('attachments'));

        if ($request->hasFile('attachments')) {
            $attachmentUploader->storeMany($repairRequest, $request->file('attachments'));
        }

        return redirect()
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '報修案件已送出。');
    }

    /**
     * 報修單詳細頁：顯示這張報修單的所有資訊、附件，以及底下的維修紀錄。
     */
    public function show(RepairRequest $repairRequest)
    {
        $repairRequest->load(['attachments', 'repairLogs.attachments']);

        return view('repair-requests.show', compact('repairRequest'));
    }

    /**
     * 人工派工（依《第 2 週個人工作計畫》第 3 項）：新報修 -> 已派工。
     * 實際的狀態檢查與寫入邏輯都在 RepairRequestWorkflow::assign()，
     * 這裡只負責接收表單資料、呼叫它、處理成功/失敗後要跳轉去哪裡。
     */
    public function assign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        try {
            $workflow->assign(
                $repairRequest,
                $request->validated('assignee_note'),
                $request->validated('scheduled_at'),
            );
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        return redirect()
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '已派工。');
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
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '已標記為處理中。');
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
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '已驗收結案。');
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
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '已退回重新處理。');
    }

    /**
     * 狀態轉換不合法時（例如使用者連點兩下、開多個分頁各按一次按鈕）會用到這個。
     * 導回詳細頁顯示友善的錯誤訊息，不讓使用者看到 Laravel 預設的 500 錯誤頁。
     */
    private function redirectWithWorkflowError(RepairRequest $repairRequest, DomainException $exception)
    {
        return redirect()
            ->route('repair-requests.show', $repairRequest)
            ->with('error', $exception->getMessage());
    }
}
