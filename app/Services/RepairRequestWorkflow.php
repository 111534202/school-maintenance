<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;           // 報修單狀態列舉
use App\Models\RepairRequest;                // 報修單資料表模型
use DomainException;                         // 「違反業務規則」的例外（狀態轉換不合法時丟出）
use Illuminate\Support\Facades\DB;           // 直接操作資料庫（這裡用它的「交易」功能）

/**
 * 案件狀態主流程（依《第 2 週個人工作計畫》第 5 項「狀態規則集中管理」）。
 *
 * 【Service（服務類別）是什麼？】把「業務規則」從 Controller 抽出來獨立放的類別。
 * Controller 只負責接請求、回畫面；「規則怎麼算」放在 Service，這樣規則只寫一份、好測試、好修改。
 *
 * 這支 class 是整個報修/維修流程「狀態機」的唯一負責人：一張報修單能不能從
 * 目前狀態改成另一個狀態，全部由這裡的 TRANSITIONS 表決定。Controller 永遠不
 * 應該自己寫 `$repairRequest->status = ...`，一律呼叫這支 class 的方法，
 * 這樣才能保證「狀態不會亂跳」（例如不會有人漏寫檢查，讓「新報修」直接
 * 變成「已結案」）。
 *
 * 五個狀態的意思（依《報修與保養流程修改說明》，拿掉了「處理中」）：
 * - pending（新報修）：使用者剛送出報修單，還沒有人接手
 * - assigned（已派工）：主管已經指定維修人員；維修人員直接填維修紀錄
 * - returned（已退回）：派工的人（新報修時）或被指派的維修人員（已派工時）退回，必填原因；流程結束，仍留在看板
 * - pending_review（待驗收）：維修人員填完維修紀錄，等報修人確認有沒有修好
 * - completed（已結案）：報修人確認修好了，整個流程結束（無法再變動）
 *
 * 完整流程圖：
 *
 *   新報修 → 已派工 → 待驗收 → 已結案
 *     │        │ ↑        │
 *     │        │ └（驗收不通過，填原因）
 *     └→ 已退回 ←┘（退回，填原因）
 *
 * 驗收不通過退回「已派工」：維修人員通常不會換，只是要回去補修；之前填過的維修紀錄（RepairLog）
 * 不會被刪除或修改，退回後維修人員會再填一筆新的維修紀錄。
 * 設備狀態不再跟著報修單改寫：「待維修」由報修單推算（見 RepairRequestStatus::awaitingRepairValues）。
 *
 * 【想改流程（例如新增一個「待料中」狀態）】
 * 1. app/Enums/RepairRequestStatus.php 加新狀態；2. 下面 TRANSITIONS 表加上它能轉去哪、誰能轉到它；
 * 3. lang/各語言資料夾/repair_requests.php 加中英文名稱；4. 新增對應的方法與 Controller 動作。
 */
class RepairRequestWorkflow
{
    /**
     * 狀態轉換規則表：key 是「目前狀態」，value 是「可以轉成的狀態清單」。
     * 例如 'pending' => ['assigned'] 表示：狀態是「新報修」時，只能轉成「已派工」，
     * 或「已退回」，不能跳過去直接變成「待驗收」或其他狀態。
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['assigned', 'returned'],
        'assigned' => ['pending_review', 'returned'],
        'pending_review' => ['completed', 'assigned'],
        'returned' => [],  // 已退回是終點（使用者需要時重新報修）
        'completed' => [], // 已結案是終點，不能再轉去任何狀態
        'aborted' => [],   // 已中止（設備淘汰）也是終點；進入這個狀態只有 abort() 一條路，不開放人工操作
    ];

    /**
     * 檢查「這張報修單現在的狀態」能不能轉成「$to 這個狀態」。
     * 只回傳 true/false，不會丟例外，適合在畫面上判斷「要不要顯示這個按鈕」。
     */
    public function canTransition(RepairRequest $repairRequest, RepairRequestStatus $to): bool
    {
        // status 欄位在 Model 裡有 enum cast，讀出來已經是 RepairRequestStatus 實例，
        // 這裡要用 ->value 當純量字串去查表，不能直接拿 enum 物件當 array key。
        $allowed = self::TRANSITIONS[$repairRequest->status->value];

        // in_array 的最後一個 true 代表嚴格比對（型別也必須一樣）。
        return in_array($to->value, $allowed, true);
    }

    /**
     * 跟 canTransition() 差別在於：這個方法「不合法就直接丟例外」，適合放在
     * Controller 裡「寫任何其他欄位之前」先呼叫一次，確保後面的程式碼一定是在
     * 合法狀態下才會執行，不合法的話整個請求會被擋下、什麼都不會寫進資料庫。
     *
     * @throws DomainException 狀態轉換不合法時丟出，訊息是給開發者/錯誤畫面看的
     */
    public function assertCanTransition(RepairRequest $repairRequest, RepairRequestStatus $to): void
    {
        if (! $this->canTransition($repairRequest, $to)) {
            // 這個訊息會被 Controller 捕捉、透過 session('error') 顯示給使用者看，
            // 所以用 label() 顯示翻譯後的中文/英文狀態名稱，不要顯示 pending/assigned 這種原始代碼。
            throw new DomainException(
                __('repair_requests.errors.invalid_transition', [
                    'from' => $repairRequest->status->label(),
                    'to' => $to->label(),
                ])
            );
        }
    }

    /**
     * 真正執行狀態轉換：檢查合法性 → 更新 status 欄位並存檔。
     * 設備狀態不跟著改：設備是否「待維修」由報修單推算。
     * 報修單結案時，若它是保養 NG 轉來的，順便檢查那張保養工單能不能完成（MaintenanceNgRepairSync）。
     */
    public function transitionTo(RepairRequest $repairRequest, RepairRequestStatus $to): RepairRequest
    {
        return DB::transaction(function () use ($repairRequest, $to): RepairRequest {
            // 第一步一定先檢查，不合法就在這裡丟例外。
            $this->assertCanTransition($repairRequest, $to);

            $repairRequest->status = $to;
            $repairRequest->save();

            if ($to->isClosed()) {
                MaintenanceNgRepairSync::repairClosed($repairRequest);
            }

            return $repairRequest;
        });
    }

    /**
     * 設備淘汰時中止還在進行中的報修單：新報修／已派工／處理中／待驗收 -> 已中止（終點）。
     * 這不是人工按鈕的流程（不在上面的轉換表裡），只給「淘汰設備的收尾」呼叫；已結案、已中止的案件原樣保留。
     */
    public function abort(RepairRequest $repairRequest): RepairRequest
    {
        if (! in_array($repairRequest->status->value, RepairRequestStatus::openValues(), true)) {
            return $repairRequest;
        }

        $repairRequest->status = RepairRequestStatus::Aborted;
        $repairRequest->save();

        return $repairRequest;
    }

    /**
     * 主管派工：新報修 -> 已派工，同時記錄維修人員（assigned_to，users.id 外鍵）
     * 與預計處理日期（scheduled_at）。維修人員一律是系統裡的真實帳號，不再另存姓名文字（避免同一件事存兩份）。
     *
     * 先檢查狀態合不合法，合法才寫欄位＋轉狀態，兩件事包在同一個 DB transaction
     * 裡（要嘛兩個都成功，要嘛兩個都不生效，不會有「寫一半」的情況）。
     *
     * 這裡曾經出過一個真實的 bug：舊版本是先把維修人員寫進資料庫、
     * 最後才檢查狀態合不合法。如果使用者重複送出表單（連點兩下、按上一頁重送），
     * 即使案件早就不是「新報修」了（例如已經是「已派工」），維修人員還是
     * 會被悄悄覆蓋成新值，即使畫面顯示操作失敗——資料跟畫面對不上。現在改成
     * 「先確認狀態合法，才動手寫欄位」，就不會有這個問題。
     */
    public function assign(RepairRequest $repairRequest, int $assignedTo, ?string $scheduledAt): RepairRequest
    {
        // 轉換表也允許「待驗收 → 已派工」（驗收不通過），所以派工要另外限定只能從「新報修」開始。
        $this->assertStatus($repairRequest, RepairRequestStatus::Pending, RepairRequestStatus::Assigned);

        // DB::transaction：裡面的動作「要嘛全部成功，要嘛全部取消」。
        return DB::transaction(function () use ($repairRequest, $scheduledAt, $assignedTo) {
            $repairRequest->assigned_to = $assignedTo;       // 維修人員的用戶編號（正式外鍵）
            $repairRequest->scheduled_at = $scheduledAt;     // 預計處理時間
            $repairRequest->save();

            return $this->transitionTo($repairRequest, RepairRequestStatus::Assigned);
        });
    }
    /**
     * 送出維修紀錄後自動進入待驗收：已派工 -> 待驗收。
     * 這個方法本身不建立維修紀錄（那是 RepairLogController 的工作），
     * 只負責「維修紀錄填完之後」把案件狀態往前推一步。
     */
    public function submitForReview(RepairRequest $repairRequest): RepairRequest
    {
        return $this->transitionTo($repairRequest, RepairRequestStatus::PendingReview);
    }

    /**
     * 報修人驗收通過，案件結案：待驗收 -> 已結案（依《第三週個人工作計畫》第 2 項）。
     * 已結案是流程的終點，之後不能再變動這張報修單的狀態。
     */
    public function complete(RepairRequest $repairRequest): RepairRequest
    {
        return $this->transitionTo($repairRequest, RepairRequestStatus::Completed);
    }

    /**
     * 報修人驗收不通過，把案件退回「已派工」讓維修人員重新處理（依《報修與保養流程修改說明》，原本退回「處理中」）。
     *
     * $reason 是退回原因（例如「還是會閃退，沒有真的修好」），一定要填，
     * 讓維修人員知道要補做什麼；存在 repair_requests.rejection_reason 欄位，
     * 每次退回會覆蓋成最新一次的原因。
     *
     * 注意：這個方法「不會」刪除或修改任何一筆既有的 RepairLog（維修紀錄）。
     * 退回後維修人員會再走一次「填寫維修紀錄」流程，產生一筆新的 RepairLog，
     * 所以同一張報修單底下會看到好幾筆維修紀錄，形成完整的處理歷史。
     */
    public function reject(RepairRequest $repairRequest, string $reason): RepairRequest
    {
        // 同樣先檢查合法性，再寫退回原因，避免「畫面說失敗、原因卻被偷偷改掉」。
        // 轉換表允許「新報修 → 已派工」（派工），所以這裡要另外限定：驗收不通過只能從「待驗收」退回。
        $this->assertStatus($repairRequest, RepairRequestStatus::PendingReview, RepairRequestStatus::Assigned);

        return DB::transaction(function () use ($repairRequest, $reason) {
            $repairRequest->rejection_reason = $reason;   // 記下退回原因（覆蓋成最新一次）
            $repairRequest->save();

            return $this->transitionTo($repairRequest, RepairRequestStatus::Assigned);
        });
    }

    /**
     * 退回報修單：新報修（由派工的人）或已派工（由被指派的維修人員）-> 已退回，必須填原因。
     * 退回後流程結束，報修單仍留在看板、不算待維修；使用者需要時重新報修。誰可以退回由 RepairRequestPolicy::returnRequest 判斷。
     */
    public function returnRequest(RepairRequest $repairRequest, string $reason, int $returnedBy): RepairRequest
    {
        $this->assertCanTransition($repairRequest, RepairRequestStatus::Returned);

        return DB::transaction(function () use ($repairRequest, $reason, $returnedBy) {
            $repairRequest->return_reason = $reason;
            $repairRequest->returned_by = $returnedBy;
            $repairRequest->returned_at = now();
            $repairRequest->save();

            return $this->transitionTo($repairRequest, RepairRequestStatus::Returned);
        });
    }

    /**
     * 限定「現在一定是 $expected 狀態」才能做這個動作（不合法就丟出跟轉換表一樣的錯誤訊息）。
     *
     * @throws DomainException
     */
    private function assertStatus(RepairRequest $repairRequest, RepairRequestStatus $expected, RepairRequestStatus $to): void
    {
        if ($repairRequest->status !== $expected) {
            throw new DomainException(__('repair_requests.errors.invalid_transition', [
                'from' => $repairRequest->status->label(),
                'to' => $to->label(),
            ]));
        }
    }

    /**
     * 重新指派（依《第四週個人工作計畫》第 1 項「重新指派操作」）：主管想把
     * 案件改指派給別的維修人員，或改一下預計處理日期，但案件本身狀態不需要
     * 跟著變動（跟 assign() 不一樣，assign() 是「新報修 -> 已派工」的狀態轉換，
     * reassign() 純粹只是換人、不改變狀態）。
     *
     * 只允許在「已派工」做重新指派——「新報修」還沒派過工，應該走 assign()；
     * 「待驗收」「已結案」代表已經在走驗收流程，「已退回」流程已結束，所以不開放。
     */
    public function reassign(RepairRequest $repairRequest, int $assignedTo, ?string $scheduledAt): RepairRequest
    {
        // 這個動作不改狀態，所以不走 TRANSITIONS 表，直接列出「允許換人的狀態」。
        $allowedStatuses = [RepairRequestStatus::Assigned];

        if (! in_array($repairRequest->status, $allowedStatuses, true)) {
            throw new DomainException(
                __('repair_requests.errors.reassign_invalid_status', ['status' => $repairRequest->status->label()])
            );
        }

        $repairRequest->assigned_to = $assignedTo;
        $repairRequest->scheduled_at = $scheduledAt;
        $repairRequest->save();

        return $repairRequest;
    }
}
