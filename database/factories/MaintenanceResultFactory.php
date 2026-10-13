<?php

namespace Database\Factories;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceResult>
 */
class MaintenanceResultFactory extends Factory
{
    protected $model = MaintenanceResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_order_id' => MaintenanceOrder::factory(),
            'result' => $this->faker->randomElement([MaintenanceResult::RESULT_OK, MaintenanceResult::RESULT_NG]),
            'executed_by' => $this->faker->name(),
            'executed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'notes' => null,
            'ng_conversion_status' => null,
        ];
    }
}
