<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 報修單與保養工單新增「取消／刪除」：取消與刪除是同一件事，做法是軟刪除（deleted_at 有時間＝已取消）。
 * 資料仍留在資料庫、操作紀錄記得是誰取消的，看板與統計不再列出；不是真的把資料列刪掉。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
