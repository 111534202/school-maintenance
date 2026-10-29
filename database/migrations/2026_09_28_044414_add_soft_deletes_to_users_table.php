<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 讓 users 表支援「軟刪除」：刪除帳號只標記 deleted_at，帳號與歷史紀錄保留，可還原。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes(); // 帳號停用改用軟刪除，避免 audit_logs/classrooms.manager_id 等歷史外鍵失效
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // down()：還原時移除 deleted_at 欄位。
            $table->dropSoftDeletes();
        });
    }
};
