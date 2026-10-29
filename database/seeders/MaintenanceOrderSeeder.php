<?php

namespace Database\Seeders;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Database\Seeder;

class MaintenanceOrderSeeder extends Seeder
{
    /**
     * 依現有的啟用中保養計畫，各產生一筆「定期」來源、狀態為「待處理」的保養工單，
     * 排定保養日期直接帶入計畫的下次到期日。
     */
    public function run(): void
    {
        MaintenancePlan::where('is_active', true)->get()->each(function (MaintenancePlan $plan) {
            MaintenanceOrder::firstOrCreate(
                [
                    'maintenance_plan_id' => $plan->id,
                    'source' => MaintenanceOrder::SOURCE_PERIODIC,
                ],
                [
                    // 工程實作修正（第 3 週任務 2）：這裡原本漏掉 device_id，只帶了
                    // device_category 文字，導致 maintenance_orders 永遠沒有實際連到
                    // devices 表，設備履歷頁查不到任何保養紀錄。改成跟 device_category
                    // 一樣直接帶入來源計畫的 device_id。
                    'device_id' => $plan->device_id,
                    'device_category' => $plan->device_category,
                    'status' => MaintenanceOrder::STATUS_PENDING,
                    'scheduled_date' => $plan->next_due_date,
                ]
            );
        });
    }
}
