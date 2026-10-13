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
            'device_category' => $this->faker->randomElement(['空調', '電力設備', '機械設備', '資訊設備']),
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ];
    }
}
