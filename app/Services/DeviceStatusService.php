<?php

namespace App\Services;

use App\Models\Device;                   // 設備資料表模型
use Illuminate\Support\Facades\DB;     // 資料庫交易
use InvalidArgumentException;            // 「傳入了不合理的參數」例外

/**
 * 設備狀態的統一寫入口：設備狀態只能是 Device::STATUSES 列出的值（正常、已淘汰、停用，由人工設定），
 * 要改狀態一律呼叫 updateStatus()，不要自己組字串寫欄位。
 *
 * 依《報修與保養流程修改說明》：
 * - 「待維修」不存在資料庫，由報修單推算（見 Device::getDisplayStatusAttribute）；報修流程不再改寫設備狀態。
 * - 「核心設備」與「核心設備故障 → 教室暫停預約」的規則已拿掉，所以這裡不再維護教室的異常旗標。
 *
 * 方法前面的 static 代表「不用先建立物件，直接寫 DeviceStatusService::updateStatus(...) 就能呼叫」。
 */
class DeviceStatusService
{
    /**
     * 改設備狀態：寫入資料庫、記操作紀錄。狀態沒變就什麼都不做（不會產生多餘的紀錄）。
     *
     * @param string|null $reason 變更原因，會當成操作紀錄的說明（不給就自動產生說明）
     */
    public static function updateStatus(Device $device, string $status, ?string $reason = null): Device
    {
        // 狀態必須是 Device::STATUSES 列出的值，亂傳就直接丟例外，避免髒資料進資料庫。
        if (! in_array($status, Device::STATUSES, true)) {
            throw new InvalidArgumentException("未知的設備狀態：{$status}");
        }

        $from = $device->status;   // 改之前的狀態，記錄用

        if ($from !== $status) {
            // 狀態與「淘汰收尾」放在同一個交易：中途失敗就整個退回，不會有「已淘汰、但還有進行中的工單」的半成品。
            DB::transaction(function () use ($device, $status, $from, $reason): void {
                $device->update(['status' => $status]);

                AuditLogger::log('status_changed', $device, ['from' => $from, 'to' => $status], $reason);

                // 設備淘汰：它所有進行中的報修、保養工單、計畫、AI 候選一併中止（之後全部唯讀）。
                if ($status === 'retired') {
                    app(RetiredDeviceCleanup::class)->run($device);
                }
            });
        }

        // refresh：重新從資料庫讀一次，確保回傳的是最新資料。
        return $device->refresh();
    }
}
