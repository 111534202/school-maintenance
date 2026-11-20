<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 依《報修與保養流程修改說明》調整保養流程：
 * - 保養計畫：「保養項目」勾選改成直接輸入「說明」（maintenance_plans.description）。
 *   保養項目的資料表與舊資料保留（只拿掉畫面）；舊計畫的說明先用它勾選的項目名稱帶入。
 * - 保養工單：拿掉「開始保養」。確認派工（指派負責人）後就是「進行中」，所以舊資料「待處理但已經指派負責人」的改成進行中。
 * - 保養工單新增「已轉報修」（converted）：回報 NG 後等轉出的報修單全部結案（已結案或已退回）才算已完成。
 *   舊資料中結果 NG、轉出的報修單還沒結案的工單改成已轉報修。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name')->comment('保養說明（要做哪些保養，取代原本勾選保養項目）');
        });

        // 舊計畫：說明用原本勾選的保養項目名稱帶入（用「、」串起來）。
        DB::table('maintenance_plans')->orderBy('id')->each(function ($plan) {
            $names = DB::table('maintenance_items')
                ->join('maintenance_plan_items', 'maintenance_items.id', '=', 'maintenance_plan_items.maintenance_item_id')
                ->where('maintenance_plan_items.maintenance_plan_id', $plan->id)
                ->orderBy('maintenance_items.name')
                ->pluck('maintenance_items.name');
            if ($names->isNotEmpty()) {
                DB::table('maintenance_plans')->where('id', $plan->id)->update(['description' => $names->implode('、')]);
            }
        });

        DB::table('maintenance_orders')->where('status', 'pending')->whereNotNull('assigned_to')->update(['status' => 'in_progress']);

        $openNgOrderIds = DB::table('maintenance_results')
            ->join('repair_requests', 'repair_requests.maintenance_result_id', '=', 'maintenance_results.id')
            ->where('maintenance_results.result', 'ng')
            ->whereNotIn('repair_requests.status', ['completed', 'returned'])
            ->whereNull('repair_requests.deleted_at')
            ->pluck('maintenance_results.maintenance_order_id')
            ->unique()
            ->all();
        if ($openNgOrderIds !== []) {
            DB::table('maintenance_orders')->whereIn('id', $openNgOrderIds)->where('status', 'completed')->update(['status' => 'converted']);
        }
    }

    public function down(): void
    {
        DB::table('maintenance_orders')->where('status', 'converted')->update(['status' => 'completed']);

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
