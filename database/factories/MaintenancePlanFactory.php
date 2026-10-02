<?php

namespace Database\Factories;

use App\Models\Device;
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

        // 第 3 週任務 2（設備履歷頁）：devices 表已經併入 develop，
        // 保養計畫改成實際掛在一台真實設備上，而不是只留一個文字類別。
        // 找不到設備（例如 devices 表還沒 seed）時退回舊的純文字類別，維持舊行為可運作。
        $device = Device::inRandomOrder()->first();

        return [
            'name' => fake()->randomElement(['空調機房', '飲水機', '電腦教室', '實驗室電力', '消防設備']).' 定期保養計畫',
            'device_id' => $device?->id,
            'device_category' => $device?->category?->name
                ?? fake()->randomElement(['空調', '電力設備', '資訊設備', '機械設備']),
            'cycle_days' => $cycleDays,
            'start_date' => $startDate,
            'next_due_date' => (clone $startDate)->modify("+{$cycleDays} days"),
            'is_active' => true,
        ];
    }
}
