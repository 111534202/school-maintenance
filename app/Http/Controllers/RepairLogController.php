<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Http\Requests\StoreRepairLogRequest;
use App\Models\RepairRequest;
use App\Services\AttachmentUploader;
use App\Services\RepairRequestWorkflow;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class RepairLogController extends Controller
{
    /**
     * 維修填單表單（依《第 2 週個人工作計畫》第 4 項）。
     */
    public function create(RepairRequest $repairRequest)
    {
        return view('repair-logs.create', compact('repairRequest'));
    }

    /**
     * 送出維修紀錄，並自動把案件推進到「待驗收」（處理中 -> 待驗收）。
     */
    public function store(StoreRepairLogRequest $request, RepairRequest $repairRequest, AttachmentUploader $attachmentUploader, RepairRequestWorkflow $workflow)
    {
        // 先確認案件目前狀態允許進到「待驗收」，不合法就直接丟例外、不寫任何東西。
        // 舊版是先建立 repair_log（可能還帶附件）才呼叫 workflow，若當下狀態不合法
        // （例如同一頁重複送出兩次），會留下一筆孤兒 repair_log 紀錄，案件狀態卻沒變
        // ——資料跟畫面顯示的結果對不上（審查抓到的真實 bug，這裡修正）。
        try {
            $workflow->assertCanTransition($repairRequest, RepairRequestStatus::PendingReview);
        } catch (DomainException $exception) {
            return redirect()
                ->route('repair-requests.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        $startedAt = Carbon::parse($request->validated('started_at'));
        $endedAt = Carbon::parse($request->validated('ended_at'));

        DB::transaction(function () use ($request, $repairRequest, $attachmentUploader, $workflow, $startedAt, $endedAt) {
            $repairLog = $repairRequest->repairLogs()->create([
                'cause' => $request->validated('cause'),
                'resolution' => $request->validated('resolution'),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                // 不用 Carbon 的 diffInMinutes()：Carbon 3.x 起預設回傳「有號數」，呼叫方向
                // 一沒對齊就會算出負值（實測踩到），直接用時間戳相減最不會出錯。
                'total_hours' => round(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600, 2),
            ]);

            if ($request->hasFile('attachments')) {
                $attachmentUploader->storeMany($repairLog, $request->file('attachments'));
            }

            $workflow->submitForReview($repairRequest);
        });

        return redirect()
            ->route('repair-requests.show', $repairRequest)
            ->with('status', '維修紀錄已送出，案件已進入待驗收。');
    }
}
