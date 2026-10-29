<?php

namespace Database\Factories;

use App\Models\RepairRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairRequest>
 */
// 產生假的報修單（給自動化測試用）：RepairRequest::factory()->create()；
// 需要特定欄位時可覆蓋，例如 RepairRequest::factory()->create(['status' => 'pending_review'])。
// （Factory 的基本觀念見 UserFactory.php 檔頭。）
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
            // 影響程度：隨機挑 low / medium / high。
            'impact_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            // 是否影響上課：隨機 true / false。
            'affects_class' => $this->faker->boolean(),
            // 預設狀態是「新報修」。
            'status' => 'pending',
            // 設備位置描述：從清單隨機挑一個。
            'device_note' => $this->faker->randomElement(['A101 投影機', 'B203 電腦教室 3 號機', 'C305 無線 AP']),
            // 地點：從清單隨機挑一個。
            'location' => $this->faker->randomElement(['A101', 'B203', 'C305']),
        ];
    }
}
