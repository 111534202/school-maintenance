<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用戶主檔（一般企業常見欄位）：
     * - department_id：所屬部門（對應 departments 表，部門被刪時設為 null）
     * - phone：聯絡電話
     * - is_active：帳號啟用／停用（停用的帳號不能登入，但資料與歷史紀錄保留）
     * - last_login_at：最後登入時間，方便管理員找出久未使用的帳號
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role_id')
                ->constrained('departments')->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
