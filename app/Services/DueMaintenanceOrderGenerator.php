<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 保養計畫自動產生工單（排程每天 00:00 跑，保養計畫存檔時也會馬上跑一次那個計畫）。
 *
 * 規則（依劉家芸確認的流程）：計畫的「下次到期日」＝下一張要產生的工單的排定保養日。工單提早一個週期產生：
 * - 新增計畫：起始日 11/01、週期 10 天 → 下次到期日 11/11；起始日已經到了（或就是今天）而且勾選啟用，
 *   存檔後馬上產生排定 11/11 的工單，下次到期日改成 11/21。
 * - 到了 11/11（上一張的排定日），產生排定 11/21 的下一張工單，下次到期日改成 12/01，以此類推。
 * - 工單在排定日前結案就結案；過了排定日還沒結案就是逾期（不影響下一張照常產生）。
 * 也就是：今天 ≥ 下次到期日 − 週期 時，產生排定在下次到期日的工單，然後下次到期日往後推一個週期。
 * 同一個計畫、同一個排定日只會有一張工單（取消掉的也算），重複執行不會疊加。停用的計畫不產生。
 */
class DueMaintenanceOrderGenerator
{
    /** 一次最多補產生幾期（伺服器很久沒跑排程時，避免一次產生太多張）。 */
    private const MAX_CATCH_UP = 24;

    /**
     * 所有啟用中的計畫都檢查一次。
     *
     * @return int 本次新產生的工單數量
     */
    public function generate(?CarbonInterface $asOf = null): int
    {
        $created = 0;

        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->orderBy('id')
            ->each(function (MaintenancePlan $plan) use (&$created, $asOf) {
                $created += $this->generateForPlan($plan, $asOf)->count();
            });

        return $created;
    }

    /**
     * 檢查一個計畫：到了該產生的日子就產生工單、把下次到期日往後推一個週期（必要時連續補幾期）。
     *
     * @return Collection<int, MaintenanceOrder> 這次新產生的工單
     */
    public function generateForPlan(MaintenancePlan $plan, ?CarbonInterface $asOf = null): Collection
    {
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $created = collect();

        for ($i = 0; $i < self::MAX_CATCH_UP; $i++) {
            $plan->refresh();
            if (! $plan->is_active || $plan->next_due_date === null || $plan->cycle_days < 1) {
                break;
            }
            // 上一張的排定日（＝下次到期日 − 週期）還沒到：還不用產生。
            if ($plan->next_due_date->copy()->subDays($plan->cycle_days)->gt($today)) {
                break;
            }

            $created = $created->concat($this->createNext($plan));
        }

        return $created;
    }

    /**
     * 產生排定在「下次到期日」的工單（同一個排定日已經有工單——包含取消掉的——就不重複產生），然後下次到期日往後推一個週期。
     * 保養計畫的「建立工單」按鈕也用這個。
     *
     * @return Collection<int, MaintenanceOrder>
     */
    public function createNext(MaintenancePlan $plan): Collection
    {
        $exists = MaintenanceOrder::withTrashed()
            ->where('maintenance_plan_id', $plan->id)
            ->whereDate('scheduled_date', $plan->next_due_date)
            ->exists();

        $order = $exists ? null : MaintenanceOrder::createFromPlan($plan);
        if ($order) {
            AuditLogger::log('created', $order, ['maintenance_plan_id' => $plan->id], '依保養計畫「' . $plan->name . '」產生保養工單 ' . $order->displayNumber() . '（排定 ' . $order->scheduled_date?->toDateString() . '）');
        }

        $plan->update(['next_due_date' => $plan->next_due_date->copy()->addDays($plan->cycle_days)->toDateString()]);

        return collect($order ? [$order] : []);
    }
}
