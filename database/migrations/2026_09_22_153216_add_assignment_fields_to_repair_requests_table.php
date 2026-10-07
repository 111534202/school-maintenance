<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 在 repair_requests 加上派工需要的兩個欄位：assignee_note（維修人員姓名文字）與 scheduled_at（預計處理時間）。
// 【歷史註解提醒】下面寫「users 表仍未合併」是當時狀況；現在派工已改用 assigned_to 外鍵（真正的用戶），assignee_note 只留作後備。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 依《第 2 週個人工作計畫》第 3 項「人工派工」新增欄位。
        // assigned_to（維修人員 id）在 Week1 就已經有欄位了，這裡只補派工需要的
        // 額外資訊；users 表仍未合併，assignee_note 先用文字記錄是誰，之後 users 表
        // 合併後再改成真正靠 assigned_to 這個 FK 查詢維修人員姓名。
        Schema::table('repair_requests', function (Blueprint $table) {
            // 維修人員姓名（文字備援）；after('assigned_to') 表示放在 assigned_to 欄位後面。
            $table->string('assignee_note')->nullable()->after('assigned_to')->comment('待補 users 表合併，先用文字記錄維修人員');
            // 預計處理日期時間。
            $table->timestamp('scheduled_at')->nullable()->after('assignee_note')->comment('預計處理日期');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            // down()：還原時刪除這兩個欄位。
            $table->dropColumn(['assignee_note', 'scheduled_at']);
        });
    }
};
