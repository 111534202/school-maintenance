<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Http\Requests\AssignRepairRequestRequest;
use App\Http\Requests\StoreRepairRequestRequest;
use App\Services\AttachmentUploader;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Http\Request;

class RepairRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * 維修案件看板（依《第 2 週個人工作計畫》第 2 項）：可依狀態與教室/地點篩選。
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

        return view('repair-requests.index', compact('repairRequests', 'statuses'));
    }

    /**
     * Show the form for creating a new resource.
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
     */
    public function show(RepairRequest $repairRequest)
    {
        $repairRequest->load(['attachments', 'repairLogs.attachments']);

        return view('repair-requests.show', compact('repairRequest'));
    }

    /**
     * 人工派工（依《第 2 週個人工作計畫》第 3 項）：新報修 -> 已派工。
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
     * 狀態轉換不合法時（例如重複送出、開多分頁），導回詳細頁顯示友善錯誤，
     * 不讓使用者看到 500 錯誤頁。
     */
    private function redirectWithWorkflowError(RepairRequest $repairRequest, DomainException $exception)
    {
        return redirect()
            ->route('repair-requests.show', $repairRequest)
            ->with('error', $exception->getMessage());
    }
}
