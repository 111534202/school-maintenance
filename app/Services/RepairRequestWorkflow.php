<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * 案件狀態主流程（依《第 2 週個人工作計畫》第 5 項「狀態規則集中管理」）。
 *
 * 這支 class 是整個報修/維修流程「狀態機」的唯一負責人：一張報修單能不能從
 * 目前狀態改成另一個狀態，全部由這裡的 TRANSITIONS 表決定。Controller 永遠不
 * 應該自己寫 `$repairRequest->status = ...`，一律呼叫這支 class 的方法，
 * 這樣才能保證「狀態不會亂跳」（例如不會有人漏寫檢查，讓「新報修」直接
 * 變成「已結案」）。
 *
 * 五個狀態的意思：
 * - pending（新報修）：使用者剛送出報修單，還沒有人接手
 * - assigned（已派工）：主管已經指定維修人員
 * - in_progress（處理中）：維修人員正在處理
 * - pending_review（待驗收）：維修人員填完維修紀錄，等報修人確認有沒有修好
 * - completed（已結案）：報修人確認修好了，整個流程結束（無法再變動）
 *
 * 完整流程圖：
 *
 *   新報修 → 已派工 → 處理中 → 待驗收 → 已結案
 *                                  └──（驗收不通過）──> 處理中
 *
 * 「驗收不通過」這條路是 Week1 就先討論好的規則：退回「處理中」而不是退回
 * 「已派工」，因為維修人員通常不會換，只是要回去補修；之前填過的維修紀錄
 * （RepairLog）不會被刪除或修改，退回後維修人員會再填一筆新的維修紀錄。
 */
class RepairRequestWorkflow
{
    /**
     * 狀態轉換規則表：key 是「目前狀態」，value 是「可以轉成的狀態清單」。
     * 例如 'pending' => ['assigned'] 表示：狀態是「新報修」時，只能轉成「已派工」，
     * 不能跳過去直接變成「處理中」或其他狀態。
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['assigned'],
        'assigned' => ['in_progress'],
        'in_progress' => ['pending_review'],
        'pending_review' => ['completed', 'in_progress'],
        'completed' => [], // 已結案是終點，不能再轉去任何狀態
    ];

    public function __construct(private DeviceStatusSync $deviceStatusSync)
    {
    }

    /**
     * 檢查「這張報修單現在的狀態」能不能轉成「$to 這個狀態」。
     * 只回傳 true/false，不會丟例外，適合在畫面上判斷「要不要顯示這個按鈕」。
     */
    public function canTransition(RepairRequest $repairRequest, RepairRequestStatus $to): bool
    {
        // status 欄位在 Model 裡有 enum cast，讀出來已經是 RepairRequestStatus 實例，
        // 這裡要用 ->value 當純量字串去查表，不能直接拿 enum 物件當 array key。
        $allowed = self::TRANSITIONS[$repairRequest->status->value] ?? [];

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
            throw new DomainException(
                "無法把案件從「{$repairRequest->status->value}」轉成「{$to->value}」，不符合合法的狀態流程。"
            );
        }
    }

    /**
     * 真正執行狀態轉換：檢查合法性 → 更新 status 欄位並存檔 → 視情況通知
     * DeviceStatusSync（讓設備狀態跟報修狀態保持同步，目前是等 devices 表
     * 合併後才會真的動到資料庫，見那支 class 的說明）。
     */
    public function transitionTo(RepairRequest $repairRequest, RepairRequestStatus $to): RepairRequest
    {
        $this->assertCanTransition($repairRequest, $to);

        $repairRequest->status = $to;
        $repairRequest->save();

        // 案件開始「處理中」：如果這台設備是核心設備，通知把設備標成「維修中」。
        if ($to === RepairRequestStatus::InProgress) {
            $this->deviceStatusSync->markUnderRepair($repairRequest);
        }

        // 案件進到「待驗收」或「已結案」：代表暫時（或永久）不用再修了，
        // 通知去檢查這台設備還有沒有「其他」進行中的案件，沒有的話才把設備
        // 狀態改回正常（可能同一台設備同時有兩張報修單在處理）。
        if (in_array($to, [RepairRequestStatus::PendingReview, RepairRequestStatus::Completed], true)) {
            $this->deviceStatusSync->markNormalIfNoActiveRepairs($repairRequest);
        }

        return $repairRequest;
    }

    /**
     * 主管派工：新報修 -> 已派工，同時記錄維修人員（assignee_note）與
     * 預計處理日期（scheduled_at）。
     *
     * 先檢查狀態合不合法，合法才寫欄位＋轉狀態，兩件事包在同一個 DB transaction
     * 裡（要嘛兩個都成功，要嘛兩個都不生效，不會有「寫一半」的情況）。
     *
     * 這裡曾經出過一個真實的 bug：舊版本是先把 assignee_note 寫進資料庫、
     * 最後才檢查狀態合不合法。如果使用者重複送出表單（連點兩下、按上一頁重送），
     * 即使案件早就不是「新報修」了（例如已經是「已派工」），assignee_note 還是
     * 會被悄悄覆蓋成新值，即使畫面顯示操作失敗——資料跟畫面對不上。現在改成
     * 「先確認狀態合法，才動手寫欄位」，就不會有這個問題。
     */
    public function assign(RepairRequest $repairRequest, ?string $assigneeNote, ?string $scheduledAt): RepairRequest
    {
        $this->assertCanTransition($repairRequest, RepairRequestStatus::Assigned);

        return DB::transaction(function () use ($repairRequest, $assigneeNote, $scheduledAt) {
            $repairRequest->assignee_note = $assigneeNote;
            $repairRequest->scheduled_at = $scheduledAt;
            $repairRequest->save();

            return $this->transitionTo($repairRequest, RepairRequestStatus::Assigned);
        });
    }

    /**
     * 維修人員開始處理：已派工 -> 處理中。
     */
    public function start(RepairRequest $repairRequest): RepairRequest
    {
        return $this->transitionTo($repairRequest, RepairRequestStatus::InProgress);
    }

    /**
     * 送出維修紀錄後自動進入待驗收：處理中 -> 待驗收。
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
     * 報修人驗收不通過，把案件退回「處理中」讓維修人員重新處理
     * （依《第三週個人工作計畫》第 3 項「驗收退回」，轉換方向是 Week1 就先決定好的規則）。
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
        $this->assertCanTransition($repairRequest, RepairRequestStatus::InProgress);

        return DB::transaction(function () use ($repairRequest, $reason) {
            $repairRequest->rejection_reason = $reason;
            $repairRequest->save();

            return $this->transitionTo($repairRequest, RepairRequestStatus::InProgress);
        });
    }

    /**
     * 重新指派（依《第四週個人工作計畫》第 1 項「重新指派操作」）：主管想把
     * 案件改指派給別的維修人員，或改一下預計處理日期，但案件本身狀態不需要
     * 跟著變動（跟 assign() 不一樣，assign() 是「新報修 -> 已派工」的狀態轉換，
     * reassign() 純粹只是換人、不改變狀態）。
     *
     * 只允許在「已派工」或「處理中」這兩個狀態做重新指派——「新報修」還沒
     * 派過工，應該走 assign()；「待驗收」「已結案」代表已經在走驗收流程，
     * 這時候換人意義不大，也容易造成混亂，所以不開放。
     */
    public function reassign(RepairRequest $repairRequest, string $assigneeNote, ?string $scheduledAt): RepairRequest
    {
        $allowedStatuses = [RepairRequestStatus::Assigned, RepairRequestStatus::InProgress];

        if (! in_array($repairRequest->status, $allowedStatuses, true)) {
            throw new DomainException(
                "案件狀態是「{$repairRequest->status->label()}」，不是「已派工」或「處理中」，不能重新指派。"
            );
        }

        $repairRequest->assignee_note = $assigneeNote;
        $repairRequest->scheduled_at = $scheduledAt;
        $repairRequest->save();

        return $repairRequest;
    }
}
