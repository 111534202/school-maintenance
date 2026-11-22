<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 取消保養工單改成「已中止」（保留紀錄、列表篩選「已中止」可以查看），不再軟刪除。
 * 以前取消（軟刪除）的工單：還原並改成已中止，跟新的做法一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_orders')->whereNotNull('deleted_at')->update(['status' => 'aborted', 'deleted_at' => null]);
    }

    public function down(): void
    {
        // 無法分辨哪些是原本軟刪除的，不還原。
    }
};
