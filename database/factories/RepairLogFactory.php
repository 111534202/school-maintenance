<?php

namespace Database\Factories;

use App\Models\RepairLog;
use App\Models\RepairRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairLog>
 */
class RepairLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-2 days', '-1 hours');
        $endedAt = (clone $startedAt)->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return [
            'repair_request_id' => RepairRequest::factory(),
            'cause' => $this->faker->sentence(),
            'resolution' => $this->faker->paragraph(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'total_hours' => round(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600, 2),
        ];
    }
}
