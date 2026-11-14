<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 保養工單新增「負責人」（assigned_to）：由有保養管理權限的人指派給有保養回報權限的人，
 * 指派之後只有負責人（與保養管理者）能回報這張工單的結果，通知鈴鐺也只提醒負責人。
 * 沒有指派（舊資料）時，維持原本「有回報權限的人都能回報」。
 * 帳號被刪除時負責人變空白（工單與保養結果仍保留）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
