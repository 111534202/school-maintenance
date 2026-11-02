<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\Device;
use App\Models\RepairRequest;
use Illuminate\Support\Facades\DB;

/**
 * 將報修單的流程狀態同步到設備狀態。
 */
class DeviceStatusSync
{
    /**
     * 報修單進入「處理中」時，將設備標記為維修中。
     * 所有設備都適用，不限核心設備。
     */
    public function markUnderRepair(RepairRequest $repairRequest): void
    {
        if (! $repairRequest->device_id) {
            return;
        }

        DB::transaction(function () use ($repairRequest): void {
            $device = Device::query()
                ->whereKey($repairRequest->device_id)
                ->lockForUpdate()
                ->first();

            // 設備不存在或已被軟刪除時，不做任何處理。
            if (! $device) {
                return;
            }

            // 不覆蓋已淘汰或停用的設備狀態。
            if (in_array($device->status, ['retired', 'disabled'], true)) {
                return;
            }

            // 已經是維修中就不重複更新。
            if ($device->status === 'repairing') {
                return;
            }

            DeviceStatusService::updateStatus(
                $device,
                'repairing',
                '報修單進入處理中'
            );
        });
    }

    /**
     * 報修單進入待驗收或已結案時：
     * 若沒有其他未結案案件，才將維修中的設備恢復正常。
     */
    public function markNormalIfNoActiveRepairs(RepairRequest $repairRequest): void
    {
        // 只有驗收通過、確實已結案的報修單，才能觸發設備恢復正常。
        if ($repairRequest->status !== RepairRequestStatus::Completed) {
            return;
        }

        if (! $repairRequest->device_id) {
            return;
        }

        DB::transaction(function () use ($repairRequest): void {
            $device = Device::query()
                ->whereKey($repairRequest->device_id)
                ->lockForUpdate()
                ->first();

            if (! $device) {
                return;
            }

            // 已淘汰或停用的設備不可自動恢復正常。
            if (in_array($device->status, ['retired', 'disabled'], true)) {
                return;
            }

            // 只有目前確實為維修中的設備，才需要恢復正常。
            if ($device->status !== 'repairing') {
                return;
            }

            // 排除目前這張報修單，避免它自己被計算為未結案案件。
            $hasOtherOpenRepairs = RepairRequest::query()
                ->where('device_id', $device->id)
                ->whereKeyNot($repairRequest->id)
                ->where('status', '!=', RepairRequestStatus::Completed->value)
                ->exists();

            if ($hasOtherOpenRepairs) {
                return;
            }

            DeviceStatusService::updateStatus(
                $device,
                'normal',
                '設備已無其他未結案報修單'
            );
        });
    }
}