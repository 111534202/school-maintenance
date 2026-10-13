<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// 【Seeder（種子資料）是什麼？】執行 php artisan db:seed（或 php artisan migrate:fresh --seed 重建整個資料庫並塞資料）時，
// 用來放入「初始資料／示範資料」的類別，讓一個全新的資料庫一開始就有身分、帳號、教室、設備可以操作。
// 這支是「總入口」：依下面的順序呼叫其他 Seeder。順序很重要，因為後面的資料會用到前面的資料（例如用戶要先有身分）。
// 測試帳號的登入名稱與密碼見 UserSeeder.php（密碼統一是 password）。這些只是示範資料，正式上線前要換掉。
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 依序執行下面這些 Seeder（由上往下）。
        $this->call([
            // 1. 先建立五個內建身分（用戶要先有身分才能建立）。
            RoleSeeder::class,
            // 2. 每個身分建立一個測試帳號。
            UserSeeder::class,
            // 3. 建立部門（教室與示範用戶會用到）。
            DepartmentSeeder::class,
            UserDemoSeeder::class, // 用戶主檔示範資料，需要部門已存在所以排在 DepartmentSeeder 後面
            // 5. 建立教室（要在部門之後）。
            ClassroomSeeder::class,
            // 6. 建立設備類別。
            DeviceCategorySeeder::class,
            // 7. 建立設備（要在教室、類別之後）。
            DeviceSeeder::class,
            MaintenanceItemSeeder::class,
            MaintenancePlanSeeder::class,
            MaintenanceOrderSeeder::class,
            // 第 3 週任務 6：多台同型號設備的保養歷史，供 Week4 AI 預測展示用。
            AiDemoDataSeeder::class,
        ]);

        // feature/repair（彭仕衡）的測試資料。
        // 知識庫示範文章。
        $this->call(KnowledgeBaseSeeder::class);
        // 報修單示範資料（涵蓋各種狀態，方便展示看板與流程）。
        $this->call(RepairRequestSeeder::class);
    }
}
