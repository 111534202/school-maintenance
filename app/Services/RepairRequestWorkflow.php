<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * 案件狀態主流程（依《第 2 週個人工作計畫》第 5 項）。
 * 合法轉換規則集中在這裡，Controller 只呼叫這個 class，不自己改 status 欄位。
 *
 * 新報修 -> 已派工 -> 處理中 -> 待驗收 -> 已結案
 *                                 └──（驗收不通過，Week1 決策#3）──> 處理中
 */
class RepairRequestWorkflow
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['assigned'],
        'assigned' => ['in_progress'],
        'in_progress' => ['pending_review'],
        'pending_review' => ['completed', 'in_progress'],
        'completed' => [],
    ];

    public function __construct(private DeviceStatusSync $deviceStatusSync)
    {
    }

    public function canTransition(RepairRequest $repairRequest, RepairRequestStatus $to): bool
    {
        // status 欄位在 Model 裡有 enum cast，讀出來已經是 RepairRequestStatus 實例，
        // 這裡要用 ->value 當純量字串去查表，不能直接拿 enum 物件當 array key。
        $allowed = self::TRANSITIONS[$repairRequest->status->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    /**
     * 供 Controller 在「寫任何其他欄位之前」先檢查狀態合不合法，不合法就直接丟例外，
     * 不寫任何東西。避免像 assign()/repair-logs 那樣，欄位寫了一半才發現狀態不合法。
     */
    public function assertCanTransition(RepairRequest $repairRequest, RepairRequestStatus $to): void
    {
        if (! $this->canTransition($repairRequest, $to)) {
            throw new DomainException(
                "無法把案件從「{$repairRequest->status->value}」轉成「{$to->value}」，不符合合法的狀態流程。"
            );
        }
    }

    public function transitionTo(RepairRequest $repairRequest, RepairRequestStatus $to): RepairRequest
    {
        $this->assertCanTransition($repairRequest, $to);

        $repairRequest->status = $to;
        $repairRequest->save();

        if ($to === RepairRequestStatus::InProgress) {
            $this->deviceStatusSync->markUnderRepair($repairRequest);
        }

        if (in_array($to, [RepairRequestStatus::PendingReview, RepairRequestStatus::Completed], true)) {
            $this->deviceStatusSync->markNormalIfNoActiveRepairs($repairRequest);
        }

        return $repairRequest;
    }

    /**
     * 主管派工：新報修 -> 已派工，同時記錄維修人員與預計處理日期。
     *
     * 先檢查狀態合不合法，合法才寫欄位＋轉狀態，兩件事包在同一個 transaction 裡。
     * 之前的版本先寫欄位、後檢查狀態，重複送出（連點兩下、按上一頁重送）會導致
     * assignee_note 被寫進資料庫、但整個操作其實因為狀態不合法而失敗——資料跟畫面
     * 顯示的結果對不上（審查抓到的真實 bug，這裡修正）。
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
     */
    public function submitForReview(RepairRequest $repairRequest): RepairRequest
    {
        return $this->transitionTo($repairRequest, RepairRequestStatus::PendingReview);
    }
}
