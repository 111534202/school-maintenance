<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\RepairRequest;

/**
 * 空間預約用：查出每間教室「目前有哪些設備在等待維修」，以及教室是否因此暫停預約。
 *
 * 列入提醒的情況（文字一律是「XX正在等待維修」，只要報修還沒結案都算）：
 * - 設備有尚未結案的報修案件 → 「投影機正在等待維修」
 * - 設備狀態是維修中（沒有報修單也算）→ 「投影機正在等待維修」
 * - 核心設備被停用／淘汰 → 「投影機已停用」（教室因此暫停預約）
 * - 報修案件沒有指定設備、只填了教室 → 「報修案件「冷氣不冷」正在等待維修」
 *
 * 暫停預約：教室被標成設備異常（核心設備維修中／停用，見 DeviceStatusService），
 * 或有核心設備的報修還沒結案（報修剛送出、尚未開始處理時也算）。
 * 暫停預約的教室仍可以查詢課表，但不能送出預約、也不能核准。
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
            if ($repair->device) {
                $seenDevices[$repair->device->id] = true;
                self::add($notices, $repair->device->classroom_id, self::deviceLabel($repair->device) . '正在等待維修', (bool) $repair->device->is_core);
            } else {
                self::add($notices, $repair->classroom_id, "報修案件「{$repair->title}」正在等待維修", false);
            }
        }

        // 2. 沒有未結案報修單、但設備本身是維修中；或核心設備停用／淘汰。
        $devices = Device::query()
            ->whereIn('classroom_id', $ids)
            ->whereIn('status', DeviceStatusService::PROBLEM_STATUSES)
            ->where(fn ($q) => $q->where('status', 'repairing')->orWhere('is_core', true))
            ->whereNotIn('id', array_keys($seenDevices))
            ->with('category')
            ->orderBy('device_code')
            ->get();

        foreach ($devices as $device) {
            $state = $device->status === 'repairing' ? '正在等待維修' : Device::statusLabel($device->status);
            self::add($notices, $device->classroom_id, self::deviceLabel($device) . $state, (bool) $device->is_core);
        }

        return $notices;
    }

    /**
     * 哪些教室目前暫停預約（設備異常旗標，或有核心設備在等待維修）。
     *
     * @param  iterable<int>  $classroomIds
     * @param  array<int, list<array{text: string, core: bool}>>|null  $notices  已經查過 for() 時可以直接傳入，省一次查詢
     * @return list<int>
     */
    public static function suspendedIds(iterable $classroomIds, ?array $notices = null): array
    {
        $ids = collect($classroomIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $notices ??= self::for($ids);

        $abnormal = Classroom::query()->whereIn('id', $ids)->where('reservation_status', '<>', 'normal')->pluck('id')->all();
        $coreIssue = collect($notices)->filter(fn ($items) => collect($items)->contains('core', true))->keys()->all();

        return collect($abnormal)->merge($coreIssue)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** 這間教室目前是否暫停預約。 */
    public static function isSuspended(int $classroomId): bool
    {
        return in_array($classroomId, self::suspendedIds([$classroomId]), true);
    }

    /** 同一間教室同樣的文字只列一次；同一句只要有一台是核心設備就標成核心。 */
    private static function add(array &$notices, ?int $classroomId, string $text, bool $core): void
    {
        if (! $classroomId) {
            return;
        }

        foreach ($notices[$classroomId] ?? [] as $i => $item) {
            if ($item['text'] === $text) {
                $notices[$classroomId][$i]['core'] = $item['core'] || $core;

                return;
            }
        }

        $notices[$classroomId][] = ['text' => $text, 'core' => $core];
    }

    /** 設備類別名稱（例如「投影機」）；沒有類別時用設備編號。 */
    private static function deviceLabel(Device $device): string
    {
        return $device->category?->name ?: $device->device_code;
    }
}
