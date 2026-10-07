<?php

namespace App\Services;

use App\Models\RepairRequest;            // 報修單資料表模型
use Illuminate\Support\Facades\Log;      // 寫入系統日誌（storage/logs/laravel.log）

/**
 * 設備狀態同步（依《第 2 週個人工作計畫》第 6 項）。
 *
 * 【目前狀況，請注意】這支 class 目前「只寫日誌，不會真的修改設備資料」。
 * 當初設計時 devices 表還沒合併，所以先做成空介面：呼叫時機都已經接好
 * （RepairRequestWorkflow 會在案件進入處理中、待驗收、已結案時呼叫），
 * 但內部只記 log。現在 devices 表已經合併進專案，要讓報修流程真的同步設備狀態時，
 * 把下面兩個方法的內容換成呼叫 DeviceStatusService::updateStatus()（它會一併寫操作紀錄、
 * 更新教室異常標記）即可，呼叫端（RepairRequestWorkflow）不需要再改。
 *
 * 提案中的狀態轉換規則（待跟林政寬確認，見 docs/repair-module-schema-notes.md）：
 * - 案件進入「處理中」時，若該設備為核心設備，設備狀態標記為「維修中」。
 * - 案件進入「待驗收」或「已結案」時，若該設備沒有其他進行中的案件，設備狀態改回「正常」。
 */
class DeviceStatusSync
{
    /**
     * 案件進入「處理中」時呼叫。目前只寫 log（不會真的改資料庫），
     * 要實作時這裡要換成：如果 $repairRequest->device_id 是核心設備，
     * 就把那台設備的 status 欄位改成 'repairing'（維修中）。
     */
    public function markUnderRepair(RepairRequest $repairRequest): void
    {
        // 這張報修單沒有綁定任何設備（例如手動輸入的案件，device_id 是空的）就什麼都不用做。
        if (! $repairRequest->device_id) {
            return;
        }

        // 寫一行日誌，記下「這裡本來要標記設備維修中」，方便開發時追蹤呼叫時機。
        Log::info('[DeviceStatusSync] 待 devices 表合併後實作：標記設備維修中', [
            'repair_request_id' => $repairRequest->id,
            'device_id' => $repairRequest->device_id,
        ]);
    }

    /**
     * 案件進入「待驗收」或「已結案」時呼叫。目前只寫 log，
     * 要實作時這裡要換成：檢查同一台設備還有沒有「其他」進行中的
     * 報修案件，如果沒有了，才把設備狀態改回 'normal'（正常）——因為同一台設備
     * 可能同時被開了兩張報修單，不能一張結案就急著把設備狀態改回正常。
     */
    public function markNormalIfNoActiveRepairs(RepairRequest $repairRequest): void
    {
        if (! $repairRequest->device_id) {
            return;
        }

        Log::info('[DeviceStatusSync] 待 devices 表合併後實作：確認無其他進行中案件後標記設備正常', [
            'repair_request_id' => $repairRequest->id,
            'device_id' => $repairRequest->device_id,
        ]);
    }
}
