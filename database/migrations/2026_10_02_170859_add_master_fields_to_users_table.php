<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * 用戶主檔（一般企業常見欄位）：
     * - department_id：所屬部門（對應 departments 表，部門被刪時設為 null）
     * - phone：聯絡電話
     * - is_active：帳號啟用／停用（停用的帳號不能登入，但資料與歷史紀錄保留）
     * - last_login_at：最後登入時間，方便管理員找出久未使用的帳號
     */
    // 套用變更：替 users 表加上部門、電話、啟用狀態、最後登入時間四個欄位。
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 所屬部門（外鍵，指向 departments；部門被刪時自動變空值）。
            $table->foreignId('department_id')->nullable()->after('role_id')
                ->constrained('departments')->nullOnDelete();
            // 聯絡電話（最長 30 字、可空）。
            $table->string('phone', 30)->nullable()->after('email');
            // 帳號是否啟用，預設啟用；停用的帳號不能登入。
            $table->boolean('is_active')->default(true)->after('password');
            // 最後登入時間（每次成功登入時更新）。
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    // 還原變更：移除這四個欄位。
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // down()：先移除外鍵約束。
            $table->dropForeign(['department_id']);
            // 再刪除這四個欄位。
            $table->dropColumn(['department_id', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
