<?php

namespace App\Services;

use App\Models\Classroom;                // 教室資料表模型
use App\Models\Device;                   // 設備資料表模型
use InvalidArgumentException;            // 「傳入了不合理的參數」例外

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
 *
 * 方法前面的 static 代表「不用先建立物件，直接寫 DeviceStatusService::updateStatus(...) 就能呼叫」。
 */
class DeviceStatusService
{
    // 「異常設備」的狀態清單（維修中、已淘汰、停用）：
    // - 核心設備處於這些狀態時，所在教室被標成「設備異常」（reservation_status = abnormal）；
    // - 教室主檔、設備主檔、主控台顯示「異常設備」時，也一律用這份清單判斷，全系統只有這一份定義。
    // 想調整哪些狀態算異常，只要改這一行。
    public const PROBLEM_STATUSES = ['repairing', 'retired', 'disabled'];

    /**
     * 改設備狀態：寫入資料庫、記操作紀錄、重新計算教室的異常標記。
     * 狀態沒變就什麼都不做（不會產生多餘的紀錄）。
     *
     * @param string|null $reason 變更原因，會當成操作紀錄的說明（不給就自動產生說明）
     */
    public static function updateStatus(Device $device, string $status, ?string $reason = null): Device
    {
        // 狀態必須是 Device::STATUSES 列出的值，亂傳就直接丟例外，避免髒資料進資料庫。
        if (!in_array($status, Device::STATUSES, true)) {
            throw new InvalidArgumentException("未知的設備狀態：{$status}");
        }

        $from = $device->status;   // 改之前的狀態，記錄用

        // 狀態真的有變才動作。
        if ($from !== $status) {
            $device->update(['status' => $status]);

            AuditLogger::log('status_changed', $device, ['from' => $from, 'to' => $status], $reason);

            // 設備狀態變了，所在教室的異常標記可能要跟著變。
            static::syncClassroomReservationStatus($device);
        }

        // refresh：重新從資料庫讀一次，確保回傳的是最新資料。
        return $device->refresh();
    }

    /** 設定或取消「核心設備」旗標：沒變就不動作，有變就記錄並重新計算教室異常標記。 */
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
        // 設備沒有所屬教室（空值）就不用算。
        if (!$classroom) {
            return;
        }

        // 問資料庫：這間教室裡，有沒有「核心設備」處於故障／淘汰／停用狀態？
        $hasProblem = $classroom->devices()
            ->where('is_core', true)
            ->whereIn('status', self::PROBLEM_STATUSES)
            ->exists();

        // 有問題 → abnormal（異常），沒問題 → normal（正常）。
        $newStatus = $hasProblem ? 'abnormal' : 'normal';

        // 只有真的不同才寫入，避免每次都白白更新資料庫。
        if ($classroom->reservation_status !== $newStatus) {
            $classroom->update(['reservation_status' => $newStatus]);
        }
    }

    /** 內部小工具：用設備找到它所在的教室，再重新計算該教室的異常標記。 */
    private static function syncClassroomReservationStatus(Device $device): void
    {
        static::syncClassroom($device->classroom);
    }
}
