<?php

namespace App\Services;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use Illuminate\Support\Collection;

/**
 * 保養 NG 轉報修（依《報修與保養流程修改說明》）。
 *
 * - 回報 NG 時要選出異常的設備（從工單的保養對象裡選：設備就是那台、設備類別就是那一類的設備，可複選），並填寫說明。
 * - 每台 NG 的設備各自建立一張「新報修」報修單（呼叫彭仕衡提供的 CreateRepairRequestFromMaintenanceNg），
 *   報修單帶入保養的說明，repair_requests.maintenance_result_id 指回這筆保養結果，報修人是回報 NG 的人。
 * - 工單狀態改成「已轉報修」；這些報修單全部結案（已結案或已退回）後，由 MaintenanceNgRepairSync 把工單改成「已完成」。
 *
 * 以前「NG 只建一張報修單、建不了就標記待轉報修再手動建單」的做法已拿掉。
 */
class NgToRepairService
{
    /**
     * 回報 NG 時可以選的設備：工單的每個保養對象——一台設備就是那台；一個設備類別就是那一類、還沒淘汰的設備；
     * 好幾個對象就合在一起（不重複，依設備編號排序）。沒有對象清單的舊工單用工單本身的設備／類別。
     *
     * @return Collection<int, Device>
     */
    public function selectableDevices(MaintenanceOrder $order): Collection
    {
        $targets = $order->targets()->get(['device_id', 'device_category_id']);
        if ($targets->isEmpty()) {
            $targets = collect([(object) ['device_id' => $order->device_id, 'device_category_id' => $order->device_id ? null : $order->device_category_id]]);
        }

        $deviceIds = $targets->pluck('device_id')->filter()->values()->all();
        $categoryIds = $targets->whereNull('device_id')->pluck('device_category_id')->filter()->values()->all();
        if ($deviceIds === [] && $categoryIds === []) {
            return collect();
        }

        // 已淘汰、已停用（軟刪除）的設備不能再報修，不列出來；指定的設備被淘汰或停用的，回報畫面另外列出「不轉報修」（skippedDevices）。
        return Device::query()->with(['category', 'classroom'])
            ->where('status', '<>', 'retired')->where('status', '<>', 'disabled')
            ->where(fn ($query) => $query
                ->whereIn('id', $deviceIds)
                ->orWhereIn('device_category_id', $categoryIds))
            ->orderBy('device_code')
            ->get();
    }

    /**
     * 工單裡指定的設備中，已經淘汰或停用、不能轉報修的（回報 NG 與保養結果畫面標示「已淘汰或停用，不轉報修」）。
     *
     * @return Collection<int, Device>
     */
    public function skippedDevices(MaintenanceOrder $order): Collection
    {
        $deviceIds = $order->targets()->pluck('device_id')->filter()->values()->all();
        if ($deviceIds === [] && $order->device_id) {
            $deviceIds = [$order->device_id];
        }

        return Device::withTrashed()->with('category')->whereKey($deviceIds)->orderBy('device_code')->get()
            ->filter(fn (Device $device) => $device->trashed() || in_array($device->status, ['retired', 'disabled'], true))
            ->values();
    }

    /**
     * 為每台 NG 的設備建立一張報修單，回傳建立的報修單。
     *
     * @param  array<int, int>  $deviceIds
     * @return Collection<int, RepairRequest>
     */
    public function convert(MaintenanceResult $result, array $deviceIds): Collection
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
        // 已淘汰或停用的設備不能再報修：略過，記在操作紀錄（回報畫面也不讓勾，這裡是保險）。
        $all = Device::withTrashed()->with('classroom')->whereKey($deviceIds)->orderBy('device_code')->get();
        $skipped = $all->filter(fn (Device $device) => ! Device::canBeMaintained($device->id));
        $devices = $all->diff($skipped);
        if ($skipped->isNotEmpty()) {
            AuditLogger::log('updated', $result, ['skipped_device_ids' => $skipped->pluck('id')->all()], '保養 NG 未轉報修：設備 ' . $skipped->pluck('device_code')->implode('、') . ' 已淘汰或停用');
        }
        if ($devices->isEmpty()) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);

            return collect();
        }

        $repairs = $devices->map(function (Device $device) use ($result, $order) {
            $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
                // 來源標記寫在故障描述最前面，給人看的：「【保養 NG 自動轉入，來源：保養工單 MO-00001】」
                //（程式要追溯時用 repair_requests.maintenance_result_id，不靠這段文字）。
                sourceLabel: '保養工單 ' . ($order?->displayNumber() ?? '—'),
                title: '保養檢查 NG：' . $device->device_code,
                description: '定期保養檢查結果為 NG（異常）。'
                    . "\n工單：" . ($order?->displayNumber() ?? '—')
                    . "\n執行人：" . ($result->executor?->name ?? '—')
                    . "\n說明：" . ($result->note ?: '（未填寫）'),
                deviceNote: trim($device->device_code . ' ' . ($device->classroom?->name ?? '')),
                deviceId: $device->id,
            );

            // 補上保養結果的關聯、設備所在教室，報修人＝回報 NG 的人（才看得到自己轉出的報修單、也能驗收）。
            $repairRequest->forceFill([
                'maintenance_result_id' => $result->id,
                'classroom_id' => $repairRequest->classroom_id ?? $device->classroom_id,
                'requester_id' => $repairRequest->requester_id ?? $result->executed_by,
            ])->save();

            AuditLogger::log('created', $repairRequest, [
                'title' => $repairRequest->title,
                'device_id' => $repairRequest->device_id,
                'source' => 'maintenance_result:' . $result->id,
            ], '保養 NG 自動建立報修單「' . $repairRequest->title . '」');

            return $repairRequest;
        });

        $result->update([
            'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_CONVERTED,
            'repair_request_id' => $repairs->first()?->id,   // 舊欄位：只記第一張（完整清單看 repair_requests.maintenance_result_id）
        ]);

        return $repairs;
    }
}
