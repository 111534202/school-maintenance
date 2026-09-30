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
        ]);

        // feature/repair（彭仕衡）的測試資料。
        $this->call(KnowledgeBaseSeeder::class);
        $this->call(RepairRequestSeeder::class);
    }
}
