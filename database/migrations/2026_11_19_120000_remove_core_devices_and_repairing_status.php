<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 依《報修與保養流程修改說明》：
 * - 設備的「維修中」（repairing）不再存在資料庫：改成「待維修」，由設備的報修單推算（已派工、待驗收）。原本維修中的設備改回正常。
 * - 拿掉「核心設備」（devices.is_core）與「核心設備故障 → 教室暫停預約」（classrooms.reservation_status）：
 *   教室有設備在報修只在預約頁顯示提醒，不再暫停預約。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('devices')->where('status', 'repairing')->update(['status' => 'normal']);

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('is_core');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('reservation_status');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->enum('reservation_status', ['normal', 'abnormal'])->default('normal')->after('is_active');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_core')->default(false)->after('status');
        });
    }
};
