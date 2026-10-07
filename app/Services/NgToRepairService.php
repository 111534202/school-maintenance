<?php

namespace App\Services;

use App\Models\MaintenanceResult;
use Illuminate\Support\Facades\Schema;

/**
 * NG 轉報修的介接點。
 *
 * 依《03_王佑恩_任務分工與執行指示》Phase 2 規則：「NG 時呼叫彭仕衡提供的報修建立介面，
 * 不直接複製 repair_requests 寫入邏輯」。彭仕衡提供的穩定入口是
 * App\Actions\CreateRepairRequestFromMaintenanceNg（見其 docs/待確認/Week3_王佑恩.md）。
 *
 * - 報修模組已併入（Action 類別存在、repair_requests 表存在）：
 *   呼叫 Action 建立「新報修」報修單，並把報修單 id 回寫到 maintenance_results.repair_request_id，
 *   狀態標為 converted；報修單描述會帶「maintenance_result:{id}」來源標記，雙向可追溯。
 * - 報修模組尚未併入：維持原行為，只標記 pending_handoff（待轉報修），不寫任何報修資料。
 *
 * convert() 可重複呼叫：已轉過（repair_request_id 有值）就不會再建第二張報修單。
 */
class NgToRepairService
{
    public function convert(MaintenanceResult $result): void
    {
        if (! $result->isNg()) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);

            return;
        }

        // 防止重複建立報修單。
        if ($result->repair_request_id !== null) {
            return;
        }

        if (! $this->repairModuleAvailable()) {
            $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_PENDING]);

            return;
        }

        $order = $result->maintenanceOrder()->with(['device', 'maintenancePlan'])->first();
        $device = $order?->device;

        $deviceNote = $device
            ? trim($device->device_code.' '.($device->name ?? ''))
            : ($order?->device_category);

        $title = '保養檢查 NG：'.($device?->device_code ?? $order?->device_category ?? '未指定設備');
        $description = '定期保養檢查結果為 NG（異常）。'
            ."\n工單：#".($order?->id ?? '—')
            ."\n執行人：".($result->executed_by ?? '—')
            ."\n說明：".($result->notes ?: '（未填寫）');

        $repairRequest = app(\App\Actions\CreateRepairRequestFromMaintenanceNg::class)->execute(
            sourceLabel: 'maintenance_result:'.$result->id,
            title: $title,
            description: $description,
            deviceNote: $deviceNote,
        );

        // 彭仕衡的 Action 目前沒有 device_id 參數；報修單 device_id 欄位是已有外鍵的正式欄位，
        // 這裡補上設備關聯，設備履歷才能撈到這張報修單。（已建議他之後在 Action 加 deviceId 參數）
        if ($device && $repairRequest->device_id === null) {
            $repairRequest->device_id = $device->id;
            $repairRequest->save();
        }

        $result->update([
            'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_CONVERTED,
            'repair_request_id' => $repairRequest->id,
        ]);
    }

    /** 彭仕衡的報修模組是否已併入並完成 migration。 */
    public function repairModuleAvailable(): bool
    {
        return class_exists(\App\Actions\CreateRepairRequestFromMaintenanceNg::class)
            && Schema::hasTable('repair_requests');
    }
}
