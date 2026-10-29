<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 讓 classrooms 表支援「軟刪除」（與 devices、users 一致）。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->softDeletes(); // 與 devices 一致採軟刪除，devices.classroom_id 等歷史外鍵不因刪除失效
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            // down()：還原時移除 deleted_at 欄位。
            $table->dropSoftDeletes();
        });
    }
};
