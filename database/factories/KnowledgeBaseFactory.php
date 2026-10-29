<?php

namespace Database\Factories;

use App\Models\KnowledgeBase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeBase>
 */
// 產生假的知識庫文章（給自動化測試用）：KnowledgeBase::factory()->create()。
// （Factory 的基本觀念見 UserFactory.php 檔頭。）
class KnowledgeBaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 隨機標題（約 4 個字詞的句子）。
            'title' => $this->faker->sentence(4),
            // 從清單中隨機挑一個分類（也可能是空值）。
            'category' => $this->faker->randomElement(['投影機', '電腦', '網路', '空調', null]),
            // 隨機段落當故障現象。
            'symptom' => $this->faker->paragraph(),
            'solution' => $this->faker->paragraph(),
            // 預設是上架狀態。
            'is_published' => true,
        ];
    }

    // 「狀態」變體：KnowledgeBase::factory()->unpublished()->create() 會產生未上架的文章。
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
