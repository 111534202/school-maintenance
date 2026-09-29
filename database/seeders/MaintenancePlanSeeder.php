<?php

namespace Database\Seeders;

use App\Models\MaintenanceItem;
use App\Models\MaintenancePlan;
use Illuminate\Database\Seeder;

class MaintenancePlanSeeder extends Seeder
{
    public function run(): void
    {
        $itemIds = MaintenanceItem::query()->pluck('id');

        if ($itemIds->isEmpty()) {
            $this->command?->warn('沒有保養項目可供關聯，請先跑 MaintenanceItemSeeder。');

            return;
        }

        MaintenancePlan::factory()
            ->count(4)
            ->create()
            ->each(function (MaintenancePlan $plan) use ($itemIds) {
                $plan->maintenanceItems()->sync(
                    $itemIds->random(min(3, $itemIds->count()))->all()
                );
            });
    }
}
