<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 忘記密碼：用註冊帳號時的 Email 寄「臨時密碼」，登入後再設定新密碼。
 *
 * - temporary_password：臨時密碼（雜湊後儲存，不存明碼），和原本的密碼分開存；
 *   所以有人亂按「忘記密碼」也不會把本人原本的密碼蓋掉、鎖在門外。臨時密碼只能用一次。
 * - temporary_password_expires_at：臨時密碼的有效期限（寄出後 30 分鐘）。
 * - is_password_change_required：用臨時密碼登入之後設成 true，在設定新密碼之前，其他頁面都進不去。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('temporary_password')->nullable()->after('password');
            $table->timestamp('temporary_password_expires_at')->nullable()->after('temporary_password');
            $table->boolean('is_password_change_required')->default(false)->after('temporary_password_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temporary_password', 'temporary_password_expires_at', 'is_password_change_required']);
        });
    }
};
