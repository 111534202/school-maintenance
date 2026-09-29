<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        $cycleDays = fake()->randomElement([30, 60, 90, 180, 365]);
        $startDate = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'name' => fake()->randomElement(['空調機房', '飲水機', '電腦教室', '實驗室電力', '消防設備']).' 定期保養計畫',
            'device_id' => null,
            'device_category' => fake()->randomElement(['空調', '電力設備', '資訊設備', '機械設備']),
            'cycle_days' => $cycleDays,
            'start_date' => $startDate,
            'next_due_date' => (clone $startDate)->modify("+{$cycleDays} days"),
            'is_active' => true,
        ];
    }
}
