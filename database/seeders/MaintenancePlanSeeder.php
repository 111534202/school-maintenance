<?php

namespace Database\Seeders;

use App\Models\MaintenancePlan;
use Illuminate\Database\Seeder;

/**
 * 保養計畫示範資料：計畫用「說明」描述要做的保養（依《報修與保養流程修改說明》，不再勾選保養項目）。
 */
class MaintenancePlanSeeder extends Seeder
{
    public function run(): void
    {
        MaintenancePlan::factory()->count(4)->create();
    }
}
