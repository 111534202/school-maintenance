<?php

namespace App\Enums;

/**
 * 案件狀態集中定義在這裡（依《第 2 週個人工作計畫》第 5 項「狀態規則集中管理，
 * 不散落 Controller」）。轉換規則本身在 App\Services\RepairRequestWorkflow。
 */
enum RepairRequestStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '新報修',
            self::Assigned => '已派工',
            self::InProgress => '處理中',
            self::PendingReview => '待驗收',
            self::Completed => '已結案',
        };
    }
}
