<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 預約改成「每個時段分別審核」：一筆申請選了三個時段，可以同意兩個、拒絕一個（拒絕要寫原因）。
 * - status：這個時段的審核結果（approved／rejected）。空白代表跟著整筆申請的狀態（舊資料、還沒審核的申請）。
 * - rejection_reason：這個時段被拒絕的原因。
 * 整筆申請的 status 會依各時段結果彙總：全部同意 approved、全部拒絕 rejected、有同意也有拒絕 partially_approved。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_reservation_slots', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('period_no');
            $table->string('rejection_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_reservation_slots', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason']);
        });
    }
};
