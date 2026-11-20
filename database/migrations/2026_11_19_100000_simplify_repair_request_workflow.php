<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 報修流程精簡（依《報修與保養流程修改說明》）：
 * - 拿掉「處理中」（in_progress）：已派工的維修人員直接填維修紀錄。原本處理中的案件改回「已派工」。
 * - 新增「已退回」（returned）：派工的人（新報修時）或被指派的維修人員（已派工時）可以退回報修單，
 *   必須填原因；退回後流程結束、仍留在看板，使用者需要時重新報修。退回原因、退回人、退回時間存在下面三個欄位
 *   （「驗收不通過」的原因仍存在 rejection_reason，兩者分開）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->text('return_reason')->nullable()->after('rejection_reason')->comment('退回原因（已退回時必填）');
            $table->foreignId('returned_by')->nullable()->after('return_reason')->constrained('users')->nullOnDelete()->comment('退回的人（users.id）');
            $table->timestamp('returned_at')->nullable()->after('returned_by')->comment('退回時間');
        });

        DB::table('repair_requests')->where('status', 'in_progress')->update(['status' => 'assigned']);
    }

    public function down(): void
    {
        DB::table('repair_requests')->where('status', 'returned')->update(['status' => 'pending']);

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['return_reason', 'returned_at']);
        });
    }
};
