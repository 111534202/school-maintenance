<?php

namespace Database\Factories;

use App\Models\RepairLog;
use App\Models\RepairRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairLog>
 */
// 產生假的維修紀錄（給自動化測試用）：RepairLog::factory()->create()。
// （Factory 的基本觀念見 UserFactory.php 檔頭。）
class RepairLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 開始時間：隨機挑 2 天前到 1 小時前之間的某個時間。
        $startedAt = $this->faker->dateTimeBetween('-2 days', '-1 hours');
        // 結束時間：開始時間再加 1~4 小時（clone 是複製一份，避免改到原本的開始時間）。
        $endedAt = (clone $startedAt)->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return [
            // 沒有指定報修單時，自動連帶建立一張假的報修單。
            'repair_request_id' => RepairRequest::factory(),
            'cause' => $this->faker->sentence(),
            'resolution' => $this->faker->paragraph(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            // 總工時 = (結束 - 開始) 的秒數 ÷ 3600，取到小數第 2 位。
            'total_hours' => round(($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600, 2),
        ];
    }
}
