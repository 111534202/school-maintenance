<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->id();
            $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
            $table->text('cause')->comment('故障原因說明');
            $table->text('resolution')->comment('處置方式');
            $table->timestamp('started_at')->nullable()->comment('處理起始時間');
            $table->timestamp('ended_at')->nullable()->comment('處理結束時間');
            $table->decimal('total_hours', 5, 2)->nullable()->comment('總工時（小時），未填時可由起訖時間推算');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_logs');
    }
};
