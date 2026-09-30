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
        // 依《第四週個人工作計畫》第 4 項：維修時如果用了備品（例如換燈泡、換網路線），
        // 先用自由文字記錄用了什麼、用了多少。劉家芸的 parts 表 / InventoryService 介面
        // 還沒確認前，不建立假的 parts 關聯或自己去扣庫存，只單純記錄文字說明。
        Schema::table('repair_logs', function (Blueprint $table) {
            $table->string('parts_used_note')->nullable()->after('resolution')
                ->comment('使用備品說明，待 InventoryService 介面確認後改為正式關聯+扣庫存');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_logs', function (Blueprint $table) {
            $table->dropColumn('parts_used_note');
        });
    }
};
