<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
// 【Factory（工廠）是什麼？】用來「批次產生假資料」的類別，主要給自動化測試用，例如 User::factory()->count(3)->create() 一次建立三個假用戶。
// definition() 定義一筆假資料預設長什麼樣子；fake() 是隨機產生姓名、信箱等內容的工具。
// 注意：這個工廠只產生姓名、信箱、密碼；測試需要「身分、部門、帳號名稱」時，要在建立時另外指定（見 tests/Concerns/InteractsWithRolesAndUsers.php）。
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    // 快取加密後的密碼：加密很耗時間，同一批假資料共用同一個加密結果，測試才不會變慢。
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 隨機姓名。
            'name' => fake()->name(),
            // 隨機信箱；unique() 確保同一批資料不會重複。
            'email' => fake()->unique()->safeEmail(),
            // 標記 Email 已驗證。
            'email_verified_at' => now(),
            // 密碼固定為 password（第一次加密後快取起來共用）。
            'password' => static::$password ??= Hash::make('password'),
            // 隨機的「記住我」token。
            'remember_token' => Str::random(10),
            // 預設是啟用中的帳號。要明確寫出來：資料庫的預設值不會回填到剛建立的物件上，
            // 而每個請求都會檢查 is_active（見 EnsureUserIsActive），少了這個欄位會被當成停用帳號。
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    // 「狀態」變體：User::factory()->unverified()->create() 會產生「Email 尚未驗證」的用戶。
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
