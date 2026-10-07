<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 保養結果 NG 轉報修後，記錄對應的報修單 id（來源可追溯：保養結果 → 報修單）。
     *
     * 刻意不加外鍵約束：repair_requests 是彭仕衡的資料表，在他的分支併入 develop 前
     * 這張表可能不存在；先用純數字欄位記錄，待全組確認後再決定是否補外鍵。
     * 反方向的追溯由彭仕衡的 Action 負責：報修單描述會寫入「maintenance_result:{id}」。
     */
    public function up(): void
    {
        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->unsignedBigInteger('repair_request_id')->nullable()->after('ng_conversion_status')
                ->comment('NG 轉報修後建立的報修單 id（無外鍵約束，見 migration 說明）');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->dropColumn('repair_request_id');
        });
    }
};
