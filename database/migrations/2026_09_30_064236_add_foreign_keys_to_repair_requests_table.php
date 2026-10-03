<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
// 外鍵（foreign key）：讓資料庫保證「報修人／設備／維修人員」指向的資料真的存在。nullOnDelete 代表對方被刪除時，這裡自動變成空值，不會連報修單一起刪。
return new class extends Migration
{
    /**
     * devices / users 表現在已經真的合併進 develop 了（林政寬的 feature/auth-device），
     * 這裡把當初先用 unsignedBigInteger 佔位的三個欄位（reporter_id / device_id /
     * assigned_to）補上正式外鍵，對齊 docs/repair-module-schema-notes.md 待確認②⑤
     * 的規劃。device_note / assignee_note 欄位保留不刪，NG 轉報修等沒有對應真實
     * 帳號/設備的案件（或舊資料）還是能用文字描述當後備顯示。
     */
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            // 報修人 → users 表。
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            // 設備 → devices 表。
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            // 維修人員 → users 表。
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    // 還原變更：移除這三個外鍵約束（欄位本身保留）。
    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            // down()：還原時依序移除這三個外鍵約束。
            $table->dropForeign(['reporter_id']);
            $table->dropForeign(['device_id']);
            $table->dropForeign(['assigned_to']);
        });
    }
};
