<?php

namespace App\Services;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use Illuminate\Support\Collection;

/**
 * 保養 NG 轉報修（依《報修與保養流程修改說明》與劉家芸的確認）。
 *
 * - 回報 NG 時要填寫說明，建立「新報修」報修單（呼叫彭仕衡提供的 CreateRepairRequestFromMaintenanceNg）：
 *   工單只有一台設備：為那台建一張；工單有設備類別或好幾個對象：回報時勾選異常的設備，每台各建一張
 *   （沒有可報修的設備可以勾時，建一張不帶設備的，維修人員填維修紀錄時再選）。
 *   報修單帶入保養的說明，repair_requests.maintenance_result_id 指回這筆保養結果，報修人是回報 NG 的人。
 * - 工單狀態改成「已轉報修」；報修單結案（已結案或已退回）後，由 MaintenanceNgRepairSync 把工單改成「已完成」。
 * - 工單的設備已淘汰或停用：不能再報修，不建報修單（標成「不需轉報修」，畫面說明原因）。
 */
class NgToRepairService
{
    /**
     * 回報 NG 時要不要勾選設備：工單只有一台設備時不用（NG 就是那台）；有設備類別、或好幾個對象時要勾出異常的設備。
     */
    public function needsDeviceChoice(MaintenanceOrder $order): bool
    {
        $items = $order->targetItems();

        return $items->count() > 1 || $items->contains(fn (array $item) => $item['device'] === null);
    }

    /**
     * 回報 NG 時可以勾的設備：工單的每個保養對象——指定的設備就是那台、設備類別就是那一類——裡還在使用（沒淘汰、沒停用）的設備，
     * 不重複、依設備編號排序。不用勾選的工單（只有一台設備）回傳空的。
     *
     * @return Collection<int, Device>
     */
    public function selectableDevices(MaintenanceOrder $order): Collection
    {
        if (! $this->needsDeviceChoice($order)) {
            return collect();
        }
        $items = $order->targetItems();
        $deviceIds = $items->pluck('device')->filter()->pluck('id')->all();
        $categoryIds = $items->filter(fn (array $item) => $item['device'] === null)->pluck('category')->filter()->pluck('id')->all();

        return Device::query()->with(['category', 'classroom'])
            ->whereNotIn('status', ['retired', 'disabled'])
            ->where(fn ($query) => $query->whereIn('id', $deviceIds)->orWhereIn('device_category_id', $categoryIds))
            ->orderBy('device_code')
            ->get();
    }

    /**
     * 為 NG 的保養結果建立報修單，回傳建立的報修單（沒有建立就是空的）。
     *
     * @param  array<int, int>  $deviceIds  整類設備的工單勾選的異常設備（一台設備的工單不用傳）
     * @return Collection<int, RepairRequest>
     */
    public function convert(MaintenanceResult $result, array $deviceIds = []): Collection
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
        $choose = $order && $this->needsDeviceChoice($order);
        // 只有一台設備的工單：NG 就是那台；那台已淘汰（或已停用）就不能再報修，不轉報修單（畫面會說明原因）。
        $device = ! $choose && $order?->device_id ? Device::withTrashed()->with('classroom')->find($order->device_id) : null;
        if ($device && ! Device::canBeMaintained($device->id)) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);
            AuditLogger::log('updated', $result, ['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE], '保養 NG 未轉報修：設備 ' . $device->device_code . ' 已淘汰或停用');

            return collect();
        }

        // 要勾選設備的工單：每台勾選的設備各建一張（只收還能報修的）；沒勾到（沒有可選的設備）就建一張不帶設備的。
        $devices = $device
            ? collect([$device])
            : Device::query()->with('classroom')->whereKey($deviceIds)->get()
                ->filter(fn (Device $d) => Device::canBeMaintained($d->id))->sortBy('device_code')->values();
        $repairs = ($devices->isEmpty() ? collect([null]) : $devices)
            ->map(fn (?Device $d) => $this->createRepair($result, $order, $d));
        $result->update([
            'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_CONVERTED,
            'repair_request_id' => $repairs->first()->id,   // 舊欄位：只記第一張（完整清單看 repair_requests.maintenance_result_id）
        ]);

        return $repairs;
    }

    /** 建立一張 NG 轉出的報修單（$device 為 null＝整類、不帶設備）。 */
    private function createRepair(MaintenanceResult $result, ?MaintenanceOrder $order, ?Device $device): RepairRequest
    {
        $target = $device?->device_code ?? ($order?->targetsLabel() ?? '—');
        $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
            // 來源標記寫在故障描述最前面，給人看的：「【保養 NG 自動轉入，來源：保養工單 MO-00001】」
            //（程式要追溯時用 repair_requests.maintenance_result_id，不靠這段文字；報修案件頁會把它做成可以點開工單的連結）。
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

        return $repairRequest;
    }
}
