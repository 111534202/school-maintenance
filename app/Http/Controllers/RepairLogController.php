<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;                // 報修單狀態列舉
use App\Http\Requests\StoreRepairLogRequest;      // 「維修填單」表單的驗證規則
use App\Models\RepairRequest;                     // 報修單資料表的模型
use App\Services\AttachmentUploader;              // 負責存放上傳附件（照片）的服務
use App\Services\AuditLogger;                     // 共用的操作紀錄寫入工具
use App\Services\RepairRequestWorkflow;           // 報修單狀態轉換規則
use Carbon\Carbon;                                // 日期時間處理工具
use DomainException;                              // 「違反業務規則」的例外（狀態轉換不合法時丟出）
use Illuminate\Support\Facades\DB;                // 直接操作資料庫（這裡用它的「交易」功能）
use Illuminate\Support\Facades\Gate;              // 權限判斷：Gate::authorize 不通過就直接回 403

/**
 * 「維修填單」的網頁功能：維修人員處理完一張報修單後，在這裡填故障原因、
 * 處置方式、處理起訖時間，並可以上傳維修前後照片。送出後案件狀態會自動
 * 從「處理中」推進到「待驗收」。
 * 需要 repairs.process 權限（見 routes/web.php），而且這張單必須是指派給自己的
 * （見 App\Policies\RepairRequestPolicy；系統管理員不受限制）。
 */
class RepairLogController extends Controller
{
    /** 顯示維修填單的空白表單，$repairRequest 是這筆維修紀錄要掛在哪一張報修單下面。 */
    public function create(RepairRequest $repairRequest)
    {
        // 只有被指派的維修人員本人（或系統管理員）能填這張單的維修紀錄，其他人 → 403。
        Gate::authorize('fillLog', $repairRequest);

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

        // 把表單送來的文字時間轉成可以計算的時間物件。
        $startedAt = Carbon::parse($request->validated('started_at'));
        $endedAt = Carbon::parse($request->validated('ended_at'));

        // 先宣告變數，等下在交易裡面把建立好的維修紀錄放進來，交易結束後才能拿來寫操作紀錄。
        $createdLog = null;

        // DB::transaction（資料庫交易）：裡面的動作「要嘛全部成功，要嘛全部取消」。
        // use (...) 是把外面的變數帶進函式裡；&$createdLog 前面的 & 表示「帶參照」，裡面改了外面也會跟著變。
        DB::transaction(function () use ($request, $repairRequest, $attachmentUploader, $workflow, $startedAt, $endedAt, &$createdLog) {
            // 在這張報修單底下建立一筆維修紀錄，同時存進 $repairLog 與 $createdLog。
            $repairLog = $createdLog = $repairRequest->repairLogs()->create([
                'cause' => $request->validated('cause'),                       // 故障原因
                'resolution' => $request->validated('resolution'),             // 處置方式
                'parts_used_note' => $request->validated('parts_used_note'),   // 更換零件備註
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                // 不用 Carbon 的 diffInMinutes()：Carbon 3.x 起預設回傳「有號數」，呼叫方向
                // 一沒對齊就會算出負值（實測踩到），直接用時間戳相減最不會出錯。
                // 算法：結束時間戳 - 開始時間戳 = 秒數，除以 3600 變小時，round(…, 2) 取到小數第 2 位。
                'total_hours' => round(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600, 2),
            ]);

            // 有上傳維修前後照片才處理，附件掛在這筆維修紀錄底下。
            if ($request->hasFile('attachments')) {
                $attachmentUploader->storeMany($repairLog, $request->file('attachments'));
            }

            // 這整個 function 包在 DB::transaction 裡：只要上面任何一步失敗（例如未來
            // 接上真正的 InventoryService 後扣庫存失敗），前面寫的 repair_log／附件都會
            // 自動回復，案件也不會被誤標記成「待驗收」——這就是《第四週個人工作計畫》
            // 第 4 項要求的「扣庫存失敗時不得把維修錯誤地完成」的安全機制。
            $workflow->submitForReview($repairRequest);
        });

        // 交易成功才記（上面失敗會整個回復，不會留下不存在的紀錄）。
        AuditLogger::log('created', $createdLog, [
            'repair_request_id' => $repairRequest->id,
            'total_hours' => $createdLog->total_hours,
        ], __('audit.messages.repair_log_created', ['title' => $repairRequest->title, 'hours' => $createdLog->total_hours]));

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_logs.flash.submitted'));
    }
}
