<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 repair_logs（維修紀錄）資料表：維修人員處理完填的故障原因、處置方式、起訖時間與工時。
// 一張報修單可以有多筆維修紀錄（驗收退回重修時會再填新的一筆）。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 依《第四週進度安排》第 3 項：repair_logs 最小 migration/model。
        // repair_request_id 指向自己這週剛建好的 repair_requests 表（自己負責的表，可以正常加外鍵），
        // 本週不做維修人員填單 UI，先讓劉家芸的 repair_part_usage 有表可以合併外鍵。
        Schema::create('repair_logs', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            // 屬於哪一張報修單（外鍵）；cascadeOnDelete 代表報修單被刪掉時，底下的維修紀錄跟著刪除。
            $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
            // 故障原因說明。
            $table->text('cause')->comment('故障原因說明');
            // 處置方式（怎麼修的）。
            $table->text('resolution')->comment('處置方式');
            // 處理起始時間。
            $table->timestamp('started_at')->nullable()->comment('處理起始時間');
            // 處理結束時間。
            $table->timestamp('ended_at')->nullable()->comment('處理結束時間');
            // 總工時（小時）：最多 5 位數、小數 2 位，由起訖時間算出來。
            $table->decimal('total_hours', 5, 2)->nullable()->comment('總工時（小時），未填時可由起訖時間推算');
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('repair_logs');
    }
};
