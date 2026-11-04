<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 第一正規化（1NF）：一個欄位只能放一個值。roles.permissions 原本是 JSON 陣列（一格放很多個權限代碼），
// 查「哪些身分有某個權限」要靠 JSON 查詢，也沒辦法對每個權限加約束。
// 這一支把它拆成獨立的 role_permissions 資料表（一列 = 一個身分的一個權限），舊資料全部搬過去，再刪掉 JSON 欄位。
//   - role_id：指向 roles（身分刪除時，勾選的權限一起刪除）
//   - permission：權限代碼，格式「功能.動作」（清單見 App\Support\PermissionCatalog）
//   - (role_id, permission) 不可重複
// 程式裡不用改寫：Role 模型的 $role->permissions 仍然是權限代碼的陣列，只是改成讀寫這張表。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission', 100);
            $table->timestamps();

            $table->unique(['role_id', 'permission']);
        });

        // 搬舊資料：JSON 陣列 → 一個權限一列（去除重複與空值）。
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $codes = array_values(array_unique(array_filter(json_decode($role->permissions ?? '[]', true) ?: [], 'is_string')));

            foreach ($codes as $code) {
                DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => $code, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }

    // 還原：把每個身分的權限組回 JSON 陣列，放回 roles.permissions。
    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->json('permissions')->nullable()->after('is_system'));

        foreach (DB::table('roles')->get(['id']) as $role) {
            $codes = DB::table('role_permissions')->where('role_id', $role->id)->orderBy('id')->pluck('permission')->all();
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($codes)]);
        }

        Schema::dropIfExists('role_permissions');
    }
};
