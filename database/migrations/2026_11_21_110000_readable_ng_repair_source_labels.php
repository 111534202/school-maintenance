<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 保養 NG 轉出的報修單，故障描述最前面原本寫「【保養 NG 自動轉入，來源：maintenance_result:28】」（程式用的代號，看起來像亂碼），
 * 改成看得懂的「【保養 NG 自動轉入，來源：保養工單 MO-00001】」。只改有記錄 maintenance_result_id 的報修單（找得到工單）。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_requests')
            ->join('maintenance_results', 'maintenance_results.id', '=', 'repair_requests.maintenance_result_id')
            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'maintenance_results.maintenance_order_id')
            ->where('repair_requests.description', 'like', '%來源：maintenance_result:%')
            ->select('repair_requests.id', 'repair_requests.description', 'maintenance_results.id as result_id', 'maintenance_orders.id as order_id', 'maintenance_orders.source')
            ->orderBy('repair_requests.id')
            ->get()
            ->each(function ($row) {
                $number = ($row->source === 'ai' ? 'AI-' : 'MO-') . str_pad((string) $row->order_id, 5, '0', STR_PAD_LEFT);
                DB::table('repair_requests')->where('id', $row->id)->update([
                    'description' => str_replace('來源：maintenance_result:' . $row->result_id . '】', '來源：保養工單 ' . $number . '】', $row->description),
                ]);
            });
    }

    public function down(): void
    {
        // 只是改顯示文字，不需要還原。
    }
};
