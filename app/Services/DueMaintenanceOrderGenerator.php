<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * 依保養計畫的下次到期日，為已到期且啟用中的計畫產生保養工單。
 *
 * 第 2 週任務 4：先支援手動執行（Artisan Command）驗證，不要求排程 daemon。
 *
 * 避免同日重複規則（工程實作決定，待全組確認）：
 * 同一張計畫、同一個 next_due_date，只會產生一筆工單；重複執行本服務不會疊加。
 * 「執行完保養後下次到期日要怎麼往前推進」原規格未定義，屬待確認事項，
 * 本服務不自動更動 maintenance_plans.next_due_date，避免搶先定義成正式規則。
 */
class DueMaintenanceOrderGenerator
{
    /**
     * @return int 本次新產生的工單數量
     */
    public function generate(?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::now();
        $created = 0;

        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $asOf->toDateString())
            ->orderBy('id')
            ->each(function (MaintenancePlan $plan) use (&$created) {
                $alreadyGenerated = MaintenanceOrder::query()
                    ->where('maintenance_plan_id', $plan->id)
                    ->whereDate('scheduled_date', $plan->next_due_date)
                    ->exists();

                if ($alreadyGenerated) {
                    return;
                }

                MaintenanceOrder::create([
                    'maintenance_plan_id' => $plan->id,
                    'device_id' => $plan->device_id,
                    'device_category' => $plan->device_category,
                    'source' => MaintenanceOrder::SOURCE_PERIODIC,
                    'status' => MaintenanceOrder::STATUS_PENDING,
                    'scheduled_date' => $plan->next_due_date,
                ]);

                $created++;
            });

        return $created;
    }
}
