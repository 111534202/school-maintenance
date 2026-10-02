<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => '系統管理員', 'slug' => 'admin', 'description' => '擁有全部權限，永遠不能被限制'],
            ['name' => '資訊組主管', 'slug' => 'it_manager', 'description' => '管理教室、設備主檔，負責派工並接收派工通知副本'],
            ['name' => '維修人員', 'slug' => 'technician', 'description' => '被指派處理報修單、填寫維修紀錄'],
            ['name' => '教師／教室管理人', 'slug' => 'teacher', 'description' => '提出報修、驗收維修結果'],
            ['name' => '主管／行政人員', 'slug' => 'executive', 'description' => '提出報修、派工與驗收'],
        ];

        foreach ($roles as $role) {
            // firstOrCreate：已經存在的身分不會被覆蓋，避免重跑 seeder 把管理員在畫面上調整過的權限洗掉。
            Role::firstOrCreate(['slug' => $role['slug']], $role + [
                'is_system' => true,
                'permissions' => PermissionCatalog::defaultsFor($role['slug']),
            ]);
        }
    }
}
