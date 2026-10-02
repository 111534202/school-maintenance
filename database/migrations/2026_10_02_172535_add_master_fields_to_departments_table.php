<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 部門主檔（一般企業常見欄位）：
     * - code：部門代碼（選填，有填的話不能重複）
     * - description：備註說明
     * - is_active：啟用／停用（停用的部門不會出現在用戶、教室的下拉選單，但既有資料保留）
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('name');
            $table->string('description')->nullable()->after('code');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'description', 'is_active']);
        });
    }
};
