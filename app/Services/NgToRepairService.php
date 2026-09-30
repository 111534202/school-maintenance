<?php

namespace App\Services;

use App\Models\MaintenanceResult;

/**
 * NG 轉報修的介接點。
 *
 * 依《03_王佑恩_任務分工與執行指示》Phase 2 規則：「NG 時呼叫彭仕衡提供的報修建立介面，
 * 不直接複製 repair_requests 寫入邏輯」。目前彭仕衡尚未提供正式的報修建立介面，
 * 所以這裡先用一個獨立的 Service 卡住這個介接點：
 * - 現在：只在 maintenance_results 上標記「待轉報修」狀態，不寫入任何 repair_requests 相關資料表。
 * - 之後：等彭仕衡的報修建立介面（Model/Service）確定後，把 convert() 內部換成呼叫他的介面即可，
 *   外部呼叫端（MaintenanceResultController）完全不用改。
 */
class NgToRepairService
{
    public function convert(MaintenanceResult $result): void
    {
        if (! $result->isNg()) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);

            return;
        }

        // TODO(待確認)：彭仕衡的報修建立介面就緒後，改為呼叫該介面並回寫真正的報修單號。
        $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_PENDING]);
    }
}
