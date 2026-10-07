<?php

namespace App\Enums;

/**
 * 案件狀態集中定義在這裡（依《第 2 週個人工作計畫》第 5 項「狀態規則集中管理，
 * 不散落 Controller」）。轉換規則本身在 App\Services\RepairRequestWorkflow。
 *
 * 【Enum（列舉）是什麼？】把「只有固定幾種可能的值」列成清單，程式裡用 RepairRequestStatus::Pending
 * 這種寫法取代容易打錯的字串 'pending'，打錯名字編輯器會立刻報錯。
 * 冒號後面的 string 表示每個選項背後存的是文字（也就是資料庫裡實際存的值）。
 *
 * 【想新增狀態】在下面加一個 case，再去 RepairRequestWorkflow 的 TRANSITIONS 表加轉換規則，
 * 並到 lang/各語言資料夾/repair_requests.php 的 status 區塊補中英文名稱。
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
        // $this->value 是這個選項背後存的文字（例如 pending），接在翻譯鍵後面就能查到對應名稱。
        return __('repair_requests.status.' . $this->value);
    }
}
