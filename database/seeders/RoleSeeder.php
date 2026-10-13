<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

// 建立系統內建的五個身分（系統管理員、資訊組主管、維修人員、教師／教室管理人、主管／行政人員）與各自的預設權限。
// 預設權限來自 App\Support\PermissionCatalog::defaultsFor()，要調整預設值改那裡；
// 管理員之後可以在「身分主檔」畫面上自行勾選調整，重跑這個 Seeder 不會覆蓋已調整過的權限。
class RoleSeeder extends Seeder
{
    // 執行這個 Seeder：建立五個內建身分與預設權限。
    public function run(): void
    {
        // 內建身分清單：name 顯示名稱、slug 內部代碼（程式判斷用，不要改）、description 說明。
        $roles = [
            ['name' => '系統管理員', 'slug' => 'admin', 'description' => '擁有全部權限，永遠不能被限制'],
            ['name' => '資訊組主管', 'slug' => 'it_manager', 'description' => '管理教室、設備主檔，負責派工並接收派工通知副本'],
            ['name' => '維修人員', 'slug' => 'technician', 'description' => '被指派處理報修單、填寫維修紀錄'],
            ['name' => '教師／教室管理人', 'slug' => 'teacher', 'description' => '提出報修、驗收維修結果'],
            ['name' => '主管／行政人員', 'slug' => 'executive', 'description' => '提出報修、派工與驗收'],
        ];

        // 逐一建立。
        foreach ($roles as $role) {
            // firstOrCreate：已經存在的身分不會被覆蓋，避免重跑 seeder 把管理員在畫面上調整過的權限洗掉。
            // firstOrCreate：用 slug 找，已存在就不動；不存在才建立（第二個參數是建立時要寫入的完整資料）。
            Role::firstOrCreate(['slug' => $role['slug']], $role + [
                // 標記為「系統內建」：之後在身分主檔不能被刪除。
                'is_system' => true,
                // 寫入這個身分的預設權限清單。
                'permissions' => PermissionCatalog::defaultsFor($role['slug']),
            ]);
        }
    }
}
