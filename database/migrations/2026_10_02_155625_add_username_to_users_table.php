<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * 登入改用「帳號名稱」（username，例如 admin、repairer）而不是 Email。
     * Email 仍保留在使用者主檔，之後由使用者主檔設定真實信箱（例如 Gmail），
     * 派工通知信等寄信功能會用 email 欄位寄送。
     * 欄位設為 nullable：舊資料不會因為新增欄位而壞掉，由 UserSeeder 或使用者主檔補值。
     */
    // 套用變更：替 users 表加上 username 欄位。
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 帳號名稱：可空（舊資料相容）、不可重複（unique）、放在 name 後面。登入時可輸入它或 Email。
            $table->string('username')->nullable()->unique()->after('name');
        });
    }

    // 還原變更：移除 username 欄位與唯一索引。
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // down()：先移除唯一索引。
            $table->dropUnique(['username']);
            // 再刪除欄位。
            $table->dropColumn('username');
        });
    }
};
