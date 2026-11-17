<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 保養工單的「進行中」狀態：負責人按「開始保養」時，狀態由待處理改為進行中，並記下開始的時間。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('scheduled_date')->comment('按下「開始保養」的時間；空白代表還沒開始');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
