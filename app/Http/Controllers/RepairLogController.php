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

/**
 * 「維修填單」的網頁功能：維修人員處理完一張報修單後，在這裡填故障原因、
 * 處置方式、處理起訖時間，並可以上傳維修前後照片。送出後案件狀態會自動
 * 從「處理中」推進到「待驗收」。
 */
class RepairLogController extends Controller
{
    /** 顯示維修填單的空白表單，$repairRequest 是這筆維修紀錄要掛在哪一張報修單下面。 */
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
                ->route('repairs.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        $startedAt = Carbon::parse($request->validated('started_at'));
        $endedAt = Carbon::parse($request->validated('ended_at'));

        DB::transaction(function () use ($request, $repairRequest, $attachmentUploader, $workflow, $startedAt, $endedAt) {
            $repairLog = $repairRequest->repairLogs()->create([
                'cause' => $request->validated('cause'),
                'resolution' => $request->validated('resolution'),
                'parts_used_note' => $request->validated('parts_used_note'),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                // 不用 Carbon 的 diffInMinutes()：Carbon 3.x 起預設回傳「有號數」，呼叫方向
                // 一沒對齊就會算出負值（實測踩到），直接用時間戳相減最不會出錯。
                'total_hours' => round(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600, 2),
            ]);

            if ($request->hasFile('attachments')) {
                $attachmentUploader->storeMany($repairLog, $request->file('attachments'));
            }

            // 這整個 function 包在 DB::transaction 裡：只要上面任何一步失敗（例如未來
            // 接上真正的 InventoryService 後扣庫存失敗），前面寫的 repair_log／附件都會
            // 自動回復，案件也不會被誤標記成「待驗收」——這就是《第四週個人工作計畫》
            // 第 4 項要求的「扣庫存失敗時不得把維修錯誤地完成」的安全機制。
            $workflow->submitForReview($repairRequest);
        });

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_logs.flash.submitted'));
    }
}
