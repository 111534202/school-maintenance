<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 在 users 表加上 role_id：每個用戶屬於哪一個身分（指向 roles 表）。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：替 users 表加上 role_id 欄位與外鍵。
    public function up(): void
    {
        // Schema::table 是「修改既有資料表」（Schema::create 才是建新表）。
        Schema::table('users', function (Blueprint $table) {
            // foreignId 建立外鍵欄位；after('id') 放在 id 欄位後面；constrained('roles') 指向 roles 表；nullOnDelete 代表身分被刪掉時，這個欄位自動變成空值。
            $table->foreignId('role_id')->nullable()->after('id')
                  ->constrained('roles')->nullOnDelete();
        });
    }

    // 還原變更：移除 role_id 欄位與外鍵。
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // down()：先移除外鍵約束，才能刪欄位。
            $table->dropForeign(['role_id']);
            // 再刪除 role_id 欄位。
            $table->dropColumn('role_id');
        });
    }
};
