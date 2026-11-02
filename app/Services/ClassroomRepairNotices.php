<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\Device;
use App\Models\RepairRequest;

/**
 * 空間預約用：查出每間教室「目前有哪些設備在報修或故障」，給申請人與審核人參考。
 *
 * 列入的情況：
 * - 設備有尚未結案的報修案件 → 「投影機（DEV-0003）正在報修（處理中）」
 * - 設備狀態是維修中（或核心設備被停用／淘汰，會讓教室暫停預約）→ 「投影機（DEV-0003）維修中」
 * - 報修案件沒有指定設備、只填了教室 → 「報修案件「冷氣不冷」（新報修）」
 *
 * 核心設備異常時教室會被標成暫停預約（見 DeviceStatusService），core 欄位讓畫面可以標示出來。
 */
class ClassroomRepairNotices
{
    /**
     * @param  iterable<int>  $classroomIds
     * @return array<int, list<array{text: string, core: bool}>> 以教室編號為鍵；沒有狀況的教室不會出現
     */
    public static function for(iterable $classroomIds): array
    {
        $ids = collect($classroomIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $notices = [];
        $seenDevices = [];

        // 1. 尚未結案的報修：設備在這些教室，或沒有設備但報修單填了這些教室。
        $repairs = RepairRequest::query()
            ->where('status', '!=', RepairRequestStatus::Completed->value)
            ->where(fn ($q) => $q
                ->whereHas('device', fn ($d) => $d->whereIn('classroom_id', $ids))
                ->orWhere(fn ($q) => $q->whereNull('device_id')->whereIn('classroom_id', $ids)))
            ->with('device.category')
            ->orderBy('id')
            ->get();

        foreach ($repairs as $repair) {
            $status = $repair->status instanceof RepairRequestStatus ? $repair->status->label() : (string) $repair->status;

            if ($repair->device) {
                if (isset($seenDevices[$repair->device->id])) {
                    continue;   // 同一台設備有多張報修單，只列一次
                }
                $seenDevices[$repair->device->id] = true;
                $notices[$repair->device->classroom_id][] = [
                    'text' => self::deviceLabel($repair->device) . " 正在報修（{$status}）",
                    'core' => (bool) $repair->device->is_core,
                ];
            } else {
                $notices[$repair->classroom_id][] = [
                    'text' => "報修案件「{$repair->title}」（{$status}）",
                    'core' => false,
                ];
            }
        }

        // 2. 沒有報修單、但設備本身是維修中；或核心設備停用／淘汰（教室因此暫停預約）。
        $devices = Device::query()
            ->whereIn('classroom_id', $ids)
            ->whereIn('status', DeviceStatusService::PROBLEM_STATUSES)
            ->where(fn ($q) => $q->where('status', 'repairing')->orWhere('is_core', true))
            ->whereNotIn('id', array_keys($seenDevices))
            ->with('category')
            ->orderBy('device_code')
            ->get();

        foreach ($devices as $device) {
            $notices[$device->classroom_id][] = [
                'text' => self::deviceLabel($device) . ' ' . Device::statusLabel($device->status),
                'core' => (bool) $device->is_core,
            ];
        }

        return $notices;
    }

    /** 「投影機（DEV-0003）」：設備類別名稱＋設備編號；沒有類別時只顯示編號。 */
    private static function deviceLabel(Device $device): string
    {
        $category = $device->category?->name;

        return $category ? "{$category}（{$device->device_code}）" : $device->device_code;
    }
}
