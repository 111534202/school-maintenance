<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            DepartmentSeeder::class,
            ClassroomSeeder::class,
            DeviceCategorySeeder::class,
            DeviceSeeder::class,
            MaintenanceItemSeeder::class,
            MaintenancePlanSeeder::class,
            MaintenanceOrderSeeder::class,
            // 第 3 週任務 6：多台同型號設備的保養歷史，供 Week4 AI 預測展示用。
            AiDemoDataSeeder::class,
        ]);
    }
}
