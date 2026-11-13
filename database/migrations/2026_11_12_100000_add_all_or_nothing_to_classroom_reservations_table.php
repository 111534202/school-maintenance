<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 空間申請改回可以逐節審核；申請人送出時可以勾選「其中一節無法借用就整筆不要」（is_all_or_nothing）：
 * 勾了以後只能整筆同意或整筆拒絕，有一節被別人借走就整筆自動拒絕。舊資料預設 false。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->boolean('is_all_or_nothing')->default(false)->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->dropColumn('is_all_or_nothing');
        });
    }
};
