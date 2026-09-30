<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Device;
use InvalidArgumentException;

/**
 * 設備狀態與核心設備旗標的統一讀寫入口。
 *
 * Week1 阻塞決策落地：
 * - 設備狀態只能是 Device::STATUSES 列出的四種值，其他模組（報修/保養）
 *   要改狀態一律呼叫 updateStatus()，不要自己組字串寫欄位。
 * - 核心設備（is_core）故障時，會自動把所在教室的 classrooms.reservation_status
 *   設為 abnormal（警示用途，本身不會阻擋預約），故障排除後自動改回 normal。
 *   這只更新 classrooms 自己的欄位，不會去動 reservations 資料，實際要不要因此
 *   擋預約是劉家芸模組的邏輯，這裡只保證她讀得到正確、即時的異常旗標。
 */
class DeviceStatusService
{
    // 核心設備處於這些狀態時，視為教室空間異常
    private const PROBLEM_STATUSES = ['repairing', 'retired', 'disabled'];

    public static function updateStatus(Device $device, string $status, ?string $reason = null): Device
    {
        if (!in_array($status, Device::STATUSES, true)) {
            throw new InvalidArgumentException("未知的設備狀態：{$status}");
        }

        $from = $device->status;

        if ($from !== $status) {
            $device->update(['status' => $status]);

            AuditLogger::log('status_changed', $device, ['from' => $from, 'to' => $status], $reason);

            static::syncClassroomReservationStatus($device);
        }

        return $device->refresh();
    }

    public static function setCore(Device $device, bool $isCore): Device
    {
        if ($device->is_core !== $isCore) {
            $device->update(['is_core' => $isCore]);

            AuditLogger::log('core_flag_changed', $device, ['is_core' => $isCore]);

            static::syncClassroomReservationStatus($device);
        }

        return $device->refresh();
    }

    /**
     * 依教室目前所有核心設備的狀態，重新計算並寫入 reservation_status。
     * 設備新增、狀態變更、核心旗標變更、換教室時都要呼叫。
     */
    public static function syncClassroom(?Classroom $classroom): void
    {
        if (!$classroom) {
            return;
        }

        $hasProblem = $classroom->devices()
            ->where('is_core', true)
            ->whereIn('status', self::PROBLEM_STATUSES)
            ->exists();

        $newStatus = $hasProblem ? 'abnormal' : 'normal';

        if ($classroom->reservation_status !== $newStatus) {
            $classroom->update(['reservation_status' => $newStatus]);
        }
    }

    private static function syncClassroomReservationStatus(Device $device): void
    {
        static::syncClassroom($device->classroom);
    }
}
