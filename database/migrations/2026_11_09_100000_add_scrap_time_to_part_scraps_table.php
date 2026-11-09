<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 報廢加上「時間」：依日期重新計算先進先出時，同一天的使用與報廢要比對時間先後。
 * 舊資料沒有時間（null），計算時用登記當下的時間代替。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_scraps', function (Blueprint $table) {
            $table->time('scrap_time')->nullable()->after('scrap_date');
        });
    }

    public function down(): void
    {
        Schema::table('part_scraps', function (Blueprint $table) {
            $table->dropColumn('scrap_time');
        });
    }
};
