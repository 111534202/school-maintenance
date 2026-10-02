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
            UserDemoSeeder::class, // 用戶主檔示範資料，需要部門已存在所以排在 DepartmentSeeder 後面
            ClassroomSeeder::class,
            DeviceCategorySeeder::class,
            DeviceSeeder::class,
        ]);

        // feature/repair（彭仕衡）的測試資料。
        $this->call(KnowledgeBaseSeeder::class);
        $this->call(RepairRequestSeeder::class);
    }
}
