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
        // 依《第三週個人工作計畫》第 3 項「驗收退回」：驗收人如果覺得設備還是有問題，
        // 要能把案件退回「處理中」重新給維修人員處理，並留下「為什麼退回」的原因，
        // 讓維修人員知道要補做什麼。這個欄位每次退回都會覆蓋成最新一次的原因；
        // 舊的維修過程紀錄不會受影響，因為那些都存在 repair_logs（一張案件可以有
        // 很多筆維修紀錄，退回後維修人員填新的一筆，舊的還在）。
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status')
                ->comment('驗收不通過退回時的原因，只保留最新一次');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
