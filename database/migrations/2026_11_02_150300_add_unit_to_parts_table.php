<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 零件新增「單位」（條、個、片…），庫存與使用明細會顯示成「數量＋單位」。
 * 既有零件先補「個」，之後可在修改基本資料時更正。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('unit', 10)->default('個')->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
