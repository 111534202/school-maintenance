<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 先進先出扣料時，同一筆維修紀錄用的同一種零件如果跨了兩個採購批次，會拆成兩列明細（各記該批的單價）。
 * 舊的唯一鍵「同一筆維修紀錄＋同一種零件只能一列」會擋掉第二列，所以拿掉。
 * 「同一張表單不能重複選同一種零件」仍由表單驗證把關。
 *
 * MySQL 的 repair_log_id 外鍵原本借用這個唯一鍵當索引，所以先補一個 repair_log_id 的一般索引再刪唯一鍵。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_part_usages', function (Blueprint $table) {
            if (! Schema::hasIndex('repair_part_usages', 'repair_part_usages_repair_log_id_index')) {
                $table->index('repair_log_id', 'repair_part_usages_repair_log_id_index');
            }
        });

        Schema::table('repair_part_usages', function (Blueprint $table) {
            if (Schema::hasIndex('repair_part_usages', 'repair_part_usage_log_part_unique')) {
                $table->dropUnique('repair_part_usage_log_part_unique');
            }
        });
    }

    public function down(): void
    {
        // 已經拆成多列的資料無法還原成一列，所以只在沒有重複時才把唯一鍵加回去。
        Schema::table('repair_part_usages', function (Blueprint $table) {
            $table->unique(['repair_log_id', 'part_id'], 'repair_part_usage_log_part_unique');
        });
    }
};
