<?php

namespace Database\Factories;

use App\Models\MaintenanceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceItem>
 */
class MaintenanceItemFactory extends Factory
{
    protected $model = MaintenanceItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                '濾網清潔', '潤滑保養', '螺絲/結構檢查', '電力安全檢測',
                '外觀清潔', '感應器校正', '軟體/韌體更新檢查', '漏水/漏電檢查',
            ]).'-'.fake()->unique()->numerify('##'),
            'category' => fake()->randomElement(['空調', '電力設備', '資訊設備', '機械設備', null]),
            'description' => fake()->optional()->sentence(),
            'default_cycle_days' => fake()->randomElement([30, 60, 90, 180, 365]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
