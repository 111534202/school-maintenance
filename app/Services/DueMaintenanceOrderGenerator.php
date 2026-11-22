<?php

namespace App\Services;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 保養計畫自動產生工單（排程每天 00:00 跑；保養計畫存檔時、下次到期日就是今天的話也會馬上跑一次那個計畫）。
 *
 * 規則（原本的做法，劉家芸確認改回）：
 * - 到了計畫的「下次到期日」當天（或已經過了），產生一張排定在下次到期日的工單。
 * - 下次到期日要等這張工單做完（回報結果）或取消後，才往後推一個週期（MaintenancePlan::advanceDueDateAfter），
 *   之後到了新的到期日再產生下一張。
 * - 同一個計畫、同一個排定日只會有一張工單（取消掉的也算），重複執行不會疊加。停用的計畫不產生。
 * - 計畫的設備已淘汰或停用：不產生（寫在操作紀錄）。
 */
class DueMaintenanceOrderGenerator
{
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
            ->whereDate('next_due_date', '<=', ($asOf ?? Carbon::now())->toDateString())
            ->orderBy('id')
            ->each(function (MaintenancePlan $plan) use (&$created, $asOf) {
                $created += $this->generateForPlan($plan, $asOf)->count();
            });

        return $created;
    }

    /**
     * 一個計畫：下次到期日到了（≤ 今天）而且這一期還沒產生過，就產生排定在下次到期日的工單。不改下次到期日。
     *
     * @return Collection<int, MaintenanceOrder> 這次產生的工單（沒有就是空的）
     */
    public function generateForPlan(MaintenancePlan $plan, ?CarbonInterface $asOf = null): Collection
    {
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $plan->refresh();
        if (! $plan->is_active || $plan->next_due_date === null || $plan->next_due_date->gt($today)) {
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

        if (! Device::canBeMaintained($plan->device_id)) {
            AuditLogger::log('updated', $plan, ['device_id' => $plan->device_id], '保養計畫「' . $plan->name . '」' . $plan->next_due_date->toDateString() . ' 這一期沒有產生工單：設備已淘汰或停用');

            return null;
        }

        $order = MaintenanceOrder::createFromPlan($plan);
        AuditLogger::log('created', $order, ['maintenance_plan_id' => $plan->id], '依保養計畫「' . $plan->name . '」產生保養工單 ' . $order->displayNumber() . '（排定 ' . $order->scheduled_date?->toDateString() . '）');

        return $order;
    }
}
