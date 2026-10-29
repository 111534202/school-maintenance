<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 新增零件使用當下的單價快照。
     */
    public function up(): void
    {
        Schema::table('repair_part_usage', function (Blueprint $table) {
            $table->unsignedInteger('unit_price')
                ->nullable()
                ->after('quantity')
                ->comment('使用零件當下的單價快照');
        });
    }

    /**
     * 回復資料表結構。
     */
    public function down(): void
    {
        Schema::table('repair_part_usage', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};