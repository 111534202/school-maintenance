<?php

namespace App\Services;

use App\Models\RepairRequest;
use Illuminate\Support\Facades\Log;

/**
 * 設備狀態同步（依《第 2 週個人工作計畫》第 6 項）。
 *
 * devices 表目前不在這個暫存專案裡（由林政寬負責、尚未合併），所以這裡先做成一個
 * 「等 devices 表真的合併後再實作」的空介面：呼叫時機都已經接好，內部先只記 log，
 * 不寫死任何假的 devices 資料表或欄位。等 devices 表合併進來後，把下面兩個方法的
 * 內容換成真正的 Device::find($id)->update(...) 即可，呼叫端（RepairRequestWorkflow）
 * 不需要再改。
 *
 * 提案中的狀態轉換規則（待跟林政寬確認，見 docs/repair-module-schema-notes.md）：
 * - 案件進入「處理中」時，若該設備為核心設備，設備狀態標記為「維修中」。
 * - 案件進入「待驗收」或「已結案」時，若該設備沒有其他進行中的案件，設備狀態改回「正常」。
 */
class DeviceStatusSync
{
    /**
     * 案件進入「處理中」時呼叫。目前只寫 log（不會真的改資料庫），
     * 等 devices 表合併後，這裡要換成：如果 $repairRequest->device_id 是核心設備，
     * 就把那台設備的 status 欄位改成 'repairing'（維修中）。
     */
    public function markUnderRepair(RepairRequest $repairRequest): void
    {
        // 目前這個暫存分支裡沒有真正的設備資料（device_id 大多是 null），
        // 如果沒有設備 id 就什麼都不用做。
        if (! $repairRequest->device_id) {
            return;
        }

        Log::info('[DeviceStatusSync] 待 devices 表合併後實作：標記設備維修中', [
            'repair_request_id' => $repairRequest->id,
            'device_id' => $repairRequest->device_id,
        ]);
    }

    /**
     * 案件進入「待驗收」或「已結案」時呼叫。目前只寫 log，
     * 等 devices 表合併後，這裡要換成：檢查同一台設備還有沒有「其他」進行中的
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
