<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 讓 devices 表支援「軟刪除」：新增 deleted_at 欄位，刪除設備時只標記時間、資料還在。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // 新增 deleted_at 欄位（軟刪除用）。
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // down()：還原時移除 deleted_at 欄位。
            $table->dropSoftDeletes();
        });
    }
};
