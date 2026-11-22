<?php

namespace App\Services;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 保養計畫自動產生工單（排程每天 00:00 跑；保養計畫存檔、重新啟用時也會馬上檢查那個計畫）。
 *
 * 規則（劉家芸確認）：
 * - 下次到期日前 LEAD_DAYS（3）天產生一張排定在下次到期日的工單，讓負責人有幾天時間派工、安排；
 *   已經在 3 天內或過了到期日的（例如剛建立的計畫、停用後重新啟用）也會馬上產生。
 * - 下次到期日要等這張工單做完（回報結果）或取消後，才往後推一個週期（MaintenancePlan::advanceDueDateAfter），
 *   推完如果新的到期日已經在 3 天內，馬上產生下一張（不用等半夜排程）；不然到了新的到期日前 3 天再產生。
 * - 同一個計畫、同一個排定日只會有一張工單（取消掉的也算），重複執行不會疊加。停用的計畫不產生。
 * - 計畫有好幾個保養對象時全部放在同一張工單；指定的設備已淘汰或停用就略過那幾台，全部都不能保養才不產生（寫在操作紀錄）。
 */
class DueMaintenanceOrderGenerator
{
    /** 提前幾天產生工單：排定日（下次到期日）前 3 天。 */
    public const LEAD_DAYS = 3;

    /**
     * 所有到期、啟用中的計畫都檢查一次。
     *
     * @return int 本次新產生的工單數量
     */
    public function generate(?CarbonInterface $asOf = null): int
    {
        $created = 0;

        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', ($asOf ?? Carbon::now())->copy()->addDays(self::LEAD_DAYS)->toDateString())
            ->orderBy('id')
            ->each(function (MaintenancePlan $plan) use (&$created, $asOf) {
                $created += $this->generateForPlan($plan, $asOf)->count();
            });

        return $created;
    }

    /**
     * 一個計畫：離下次到期日 3 天內（或已經過了）而且這一期還沒產生過，就產生排定在下次到期日的工單。不改下次到期日。
     *
     * @return Collection<int, MaintenanceOrder> 這次產生的工單（沒有就是空的）
     */
    public function generateForPlan(MaintenancePlan $plan, ?CarbonInterface $asOf = null): Collection
    {
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $plan->refresh();
        if (! $plan->is_active || $plan->next_due_date === null || $plan->next_due_date->gt($today->copy()->addDays(self::LEAD_DAYS))) {
            return collect();
        }

        $order = $this->createForCurrentCycle($plan);

        return collect($order ? [$order] : []);
    }

    /**
     * 產生計畫目前這一期（排定日＝下次到期日）的工單；這一期已經有（含已取消）或設備已淘汰、停用就不產生，回傳 null。
     */
    public function createForCurrentCycle(MaintenancePlan $plan): ?MaintenanceOrder
    {
        $exists = MaintenanceOrder::withTrashed()
            ->where('maintenance_plan_id', $plan->id)
            ->whereDate('scheduled_date', $plan->next_due_date)
            ->exists();
        if ($exists) {
            return null;
        }

        // 指定的設備已淘汰或停用：這一期略過那幾台（全部對象都不能保養就不產生工單），寫在操作紀錄。
        $skipped = collect($plan->orderTargets())->pluck('device_id')->filter()
            ->reject(fn (int $deviceId) => Device::canBeMaintained($deviceId))->values();
        $skippedCodes = $skipped->isEmpty() ? '' : Device::withTrashed()->whereKey($skipped->all())->orderBy('device_code')->pluck('device_code')->implode('、');

        $order = MaintenanceOrder::createFromPlan($plan);
        if ($order === null) {
            AuditLogger::log('updated', $plan, ['skipped_device_ids' => $skipped->all()], '保養計畫「' . $plan->name . '」' . $plan->next_due_date->toDateString() . ' 這一期沒有產生工單：設備已淘汰或停用');

            return null;
        }
        AuditLogger::log('created', $order, ['maintenance_plan_id' => $plan->id, 'skipped_device_ids' => $skipped->all()], '依保養計畫「' . $plan->name . '」產生保養工單 ' . $order->displayNumber() . '（排定 ' . $order->scheduled_date?->toDateString() . '）'
            . ($skippedCodes === '' ? '' : '；略過已淘汰或停用的設備 ' . $skippedCodes));

        return $order;
    }
}
