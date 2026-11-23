<?php

namespace Database\Factories;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceOrder>
 */
class MaintenanceOrderFactory extends Factory
{
    protected $model = MaintenanceOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'device_id' => null,
            'device_category_id' => null,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            // 建立時間預設在一年前：回報保養結果的執行時間不能早於工單建立時間，測試才好填時間。
            'created_at' => now()->subYear(),
        ];
    }
}
