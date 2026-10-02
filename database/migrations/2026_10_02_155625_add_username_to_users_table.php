<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 登入改用「帳號名稱」（username，例如 admin、repairer）而不是 Email。
     * Email 仍保留在使用者主檔，之後由使用者主檔設定真實信箱（例如 Gmail），
     * 派工通知信等寄信功能會用 email 欄位寄送。
     * 欄位設為 nullable：舊資料不會因為新增欄位而壞掉，由 UserSeeder 或使用者主檔補值。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
