<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

/**
 * 保養完成率查詢介面（第 3 週任務 4）。
 *
 * 給劉家芸的 Dashboard 模組呼叫；之後他的分支併進 develop 後，直接在他的 controller/service
 * 裡 inject 這個 Service 呼叫就好，不用重寫一份查詢邏輯。
 *
 * 分母／期間公式（工程實作決定）：
 *   《個人工作計畫》要求「分母/期間依 Week1 鎖定公式」，但目前沒有收到全組對這個公式的正式書面結論，
 *   為了不要卡住本週交付，先用最直覺的定義頂著，並在這裡明確標記成工程實作決定，等全組有正式結論
 *   後再回來調整這個 Service 就好（呼叫端的介面簽章不用變）：
 *     - 分母：排定保養日期（scheduled_date）落在指定期間內的保養工單總數。
 *     - 分子：其中狀態為「已完成」（status = completed）的工單數。
 *     - 完成率 = 分子 / 分母，分母為 0 時回傳 null（不要硬算出 0% 誤導）。
 *   預設期間：沒有指定 from/to 時，用「本月」（今天所在月份的第一天到最後一天）。
 */
class MaintenanceCompletionRateService
{
    /**
     * @param  int|null  $deviceId  只看特定設備時帶入；null 代表全部設備
     * @return array{
     *     from: string,
     *     to: string,
     *     device_id: int|null,
     *     total: int,
     *     completed: int,
     *     rate: float|null,
     * }
     */
    public function forPeriod(?Carbon $from = null, ?Carbon $to = null, ?int $deviceId = null): array
    {
        $from = ($from ?? Carbon::now()->startOfMonth())->startOfDay();
        $to = ($to ?? Carbon::now()->endOfMonth())->endOfDay();

        $query = MaintenanceOrder::query()
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId));

        $total = (clone $query)->count();
        $completed = (clone $query)->completed()->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'device_id' => $deviceId,
            'total' => $total,
            'completed' => $completed,
            'rate' => $total > 0 ? round($completed / $total, 4) : null,
        ];
    }
}
