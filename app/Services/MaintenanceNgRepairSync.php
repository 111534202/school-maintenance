<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;

/**
 * 保養 NG 轉報修的狀態同步（依《報修與保養流程修改說明》）：
 * 保養回報 NG 時，每台 NG 的設備各自建立一張報修單（repair_requests.maintenance_result_id 指回那筆保養結果），
 * 保養工單變成「已轉報修」；等這些報修單全部結束（已結案；已退回也算結束，流程不會再往下走），保養工單才變成「已完成」。
 */
class MaintenanceNgRepairSync
{
    /** 報修單結束（已結案或已退回）時呼叫：若它是保養 NG 轉來的，檢查那張保養工單能不能完成。 */
    public static function repairClosed(RepairRequest $repairRequest): void
    {
        $resultId = $repairRequest->maintenance_result_id ?? null;
        if (! $resultId) {
            return;
        }

        $result = MaintenanceResult::with('maintenanceOrder')->find($resultId);
        if ($result) {
            self::refreshOrder($result);
        }
    }

    /** 依 NG 轉出的報修單目前狀態，決定保養工單是「已轉報修」還是「已完成」。 */
    public static function refreshOrder(MaintenanceResult $result): void
    {
        $order = $result->maintenanceOrder;
        if (! $order || ! $result->isNg()) {
            return;
        }

        $repairs = $result->repairRequests()->get(['id', 'status']);
        if ($repairs->isEmpty()) {
            return;
        }

        $allClosed = $repairs->every(fn (RepairRequest $repair) => in_array($repair->status->value, RepairRequestStatus::closedValues(), true));
        $status = $allClosed ? MaintenanceOrder::STATUS_COMPLETED : MaintenanceOrder::STATUS_CONVERTED;

        if ($order->status !== $status) {
            $order->forceFill(['status' => $status])->save();
        }
    }
}
