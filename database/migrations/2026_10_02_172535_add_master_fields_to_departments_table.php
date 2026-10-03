<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * 部門主檔（一般企業常見欄位）：
     * - code：部門代碼（選填，有填的話不能重複）
     * - description：備註說明
     * - is_active：啟用／停用（停用的部門不會出現在用戶、教室的下拉選單，但既有資料保留）
     */
    // 套用變更：替 departments 表加上代碼、說明、啟用狀態三個欄位。
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // 部門代碼：最長 30 字、可空、有填就不可重複。
            $table->string('code', 30)->nullable()->unique()->after('name');
            // 備註說明（可空）。
            $table->string('description')->nullable()->after('code');
            // 是否啟用，預設啟用。
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    // 還原變更：移除這三個欄位。
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // down()：先移除唯一索引。
            $table->dropUnique(['code']);
            // 再刪除這三個欄位。
            $table->dropColumn(['code', 'description', 'is_active']);
        });
    }
};
