<?php

namespace App\Services;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\Device;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use Illuminate\Support\Collection;

/**
 * 保養 NG 轉報修（依《報修與保養流程修改說明》，劉家芸確認改回「一張工單建一張報修單」）。
 *
 * - 回報 NG 時要填寫說明；整張工單建立一張「新報修」報修單（呼叫彭仕衡提供的 CreateRepairRequestFromMaintenanceNg）：
 *   工單是一台設備就帶那台；是設備類別就不帶設備（維修人員填維修紀錄時再選是哪一台）。
 *   報修單帶入保養的說明，repair_requests.maintenance_result_id 指回這筆保養結果，報修人是回報 NG 的人。
 * - 工單狀態改成「已轉報修」；報修單結案（已結案或已退回）後，由 MaintenanceNgRepairSync 把工單改成「已完成」。
 * - 工單的設備已淘汰或停用：不能再報修，不建報修單（標成「不需轉報修」，畫面說明原因）。
 */
class NgToRepairService
{
    /**
     * 為 NG 的保養結果建立一張報修單，回傳建立的報修單（沒有建立就是空的）。
     *
     * @return Collection<int, RepairRequest>
     */
    public function convert(MaintenanceResult $result): Collection
    {
        if (! $result->isNg()) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);

            return collect();
        }

        // 已經轉過就不重複建立（例如重送）。
        if ($result->repairRequests()->exists()) {
            return $result->repairRequests()->get();
        }

        $order = $result->maintenanceOrder()->first();
        $device = $order?->device_id ? Device::withTrashed()->with('classroom')->find($order->device_id) : null;

        // 設備已淘汰（或已停用）：不能再報修，NG 不轉報修單（標成「不需轉報修」，畫面會說明原因）。
        if ($device && ! Device::canBeMaintained($device->id)) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);
            AuditLogger::log('updated', $result, ['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE], '保養 NG 未轉報修：設備 ' . $device->device_code . ' 已淘汰或停用');

            return collect();
        }

        $target = $device?->device_code ?? (($order?->deviceCategory?->name ?? '—') . '（整類）');
        $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
            // 來源標記寫在故障描述最前面，給人看的：「【保養 NG 自動轉入，來源：保養工單 MO-00001】」
            //（程式要追溯時用 repair_requests.maintenance_result_id，不靠這段文字）。
            sourceLabel: '保養工單 ' . ($order?->displayNumber() ?? '—'),
            title: '保養檢查 NG：' . $target,
            description: '定期保養檢查結果為 NG（異常）。'
                . "\n工單：" . ($order?->displayNumber() ?? '—')
                . "\n保養對象：" . ($order?->targetsLabel() ?? '—')
                . "\n執行人：" . ($result->executor?->name ?? '—')
                . "\n說明：" . ($result->note ?: '（未填寫）'),
            deviceNote: $device ? trim($device->device_code . ' ' . ($device->classroom?->name ?? '')) : $target,
            deviceId: $device?->id,
        );

        // 補上保養結果的關聯、設備所在教室，報修人＝回報 NG 的人（才看得到自己轉出的報修單、也能驗收）。
        $repairRequest->forceFill([
            'maintenance_result_id' => $result->id,
            'classroom_id' => $repairRequest->classroom_id ?? $device?->classroom_id,
            'requester_id' => $repairRequest->requester_id ?? $result->executed_by,
        ])->save();

        AuditLogger::log('created', $repairRequest, [
            'title' => $repairRequest->title,
            'device_id' => $repairRequest->device_id,
            'source' => 'maintenance_result:' . $result->id,
        ], '保養 NG 自動建立報修單「' . $repairRequest->title . '」');

        $result->update([
            'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_CONVERTED,
            'repair_request_id' => $repairRequest->id,
        ]);

        return collect([$repairRequest]);
    }
}
