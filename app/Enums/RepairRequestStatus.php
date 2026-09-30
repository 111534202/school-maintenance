<?php

namespace App\Enums;

/**
 * 案件狀態集中定義在這裡（依《第 2 週個人工作計畫》第 5 項「狀態規則集中管理，
 * 不散落 Controller」）。轉換規則本身在 App\Services\RepairRequestWorkflow。
 */
enum RepairRequestStatus: string
{
    /** 新報修：使用者剛送出，還沒有人接手 */
    case Pending = 'pending';
    /** 已派工：主管已經指定維修人員 */
    case Assigned = 'assigned';
    /** 處理中：維修人員正在處理 */
    case InProgress = 'in_progress';
    /** 待驗收：維修人員填完維修紀錄，等報修人確認有沒有修好 */
    case PendingReview = 'pending_review';
    /** 已結案：報修人驗收通過，流程結束（不能再變動） */
    case Completed = 'completed';

    /**
     * 把狀態代碼轉成畫面上要顯示的文字，例如 'pending' -> '新報修'（或英文 'New'，
     * 依目前語言而定）。翻譯文字放在 lang/{locale}/repair_requests.php 的 status 區塊，
     * i18n 新增語言時只需要在那邊補一份翻譯，這裡不用改。
     */
    public function label(): string
    {
        return __('repair_requests.status.' . $this->value);
    }
}
