<?php

namespace Database\Factories;

use App\Models\RepairRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairRequest>
 */
class RepairRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'impact_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'affects_class' => $this->faker->boolean(),
            'status' => 'pending',
            'device_note' => $this->faker->randomElement(['A101 投影機', 'B203 電腦教室 3 號機', 'C305 無線 AP']),
            'location' => $this->faker->randomElement(['A101', 'B203', 'C305']),
        ];
    }
}
