<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // 用 exists() 檢查避免重複跑 seed 時因 email 唯一鍵衝突而中斷。
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call([
            MaintenanceItemSeeder::class,
            MaintenancePlanSeeder::class,
            MaintenanceOrderSeeder::class,
        ]);
    }
}
