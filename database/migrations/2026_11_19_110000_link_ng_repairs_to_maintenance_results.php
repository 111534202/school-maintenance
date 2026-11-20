<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 保養 NG 可以轉出好幾張報修單（每台 NG 的設備各一張，依《報修與保養流程修改說明》）：
 * 改由 repair_requests.maintenance_result_id 指回那筆保養結果（一筆結果 → 多張報修單）。
 * 舊資料（maintenance_results.repair_request_id，一筆結果只會有一張）一併搬過來；舊欄位保留，不再寫入。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->foreignId('maintenance_result_id')->nullable()->after('classroom_id')
                ->constrained('maintenance_results')->nullOnDelete()
                ->comment('保養 NG 轉來的報修單：指回那筆保養結果（maintenance_results.id）');
        });

        DB::table('maintenance_results')->whereNotNull('repair_request_id')->orderBy('id')
            ->each(function ($result) {
                DB::table('repair_requests')->where('id', $result->repair_request_id)->update(['maintenance_result_id' => $result->id]);
            });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_result_id');
        });
    }
};
