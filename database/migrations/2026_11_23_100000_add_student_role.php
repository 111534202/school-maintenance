<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
// 新增系統內建的「學生」身分：只能借教室（空間預約，所有登入者都能申請）與提出報修。
// 種子資料（RoleSeeder）只有 migrate:fresh --seed 才會寫入，「已經建好的資料庫」升級後不會有這個身分，
// 所以這支 migration 把它補進去。只做「新增」不做「覆蓋」：已經有 student 身分（例如管理員自己建過）就不動，重跑也不會重複。
return new class extends Migration
{
    // 預設權限寫死在這裡，不受之後改動 PermissionCatalog 影響：學生只能報修（借教室不需要勾選權限），不能驗收。
    private const PERMISSIONS = ['repairs.create'];

    public function up(): void
    {
        if (DB::table('roles')->where('role_code', 'student')->exists()) {
            return;
        }

        $roleId = DB::table('roles')->insertGetId([
            'name' => '學生',
            'role_code' => 'student',
            'description' => '只能借教室（空間預約）與提出報修，沒有其他功能（包含不能驗收）',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::PERMISSIONS as $permission) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    // 還原：還沒有人使用這個身分才刪除（有學生帳號在用就保留，避免帳號沒有身分）。
    public function down(): void
    {
        $role = DB::table('roles')->where('role_code', 'student')->first();
        if ($role !== null && ! DB::table('users')->where('role_id', $role->id)->exists()) {
            DB::table('roles')->where('id', $role->id)->delete();   // 權限（role_permissions）會跟著刪除
        }
    }
};
