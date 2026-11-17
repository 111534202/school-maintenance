<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 指派驗收人：派工時可以指定由誰來驗收這張報修單。
 * 沒指定（空白）就維持原本的做法：由報修人本人驗收。除了系統管理員，只有報修人、維修人員、被指定的驗收人看得到
 * 「待驗收」與「已結案」的案件。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->foreignId('accepted_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete()
                ->comment('指定的驗收人（users.id）；空白代表由報修人本人驗收');
        });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by');
        });
    }
};
