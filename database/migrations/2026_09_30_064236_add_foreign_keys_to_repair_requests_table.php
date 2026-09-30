<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropForeign(['reporter_id']);
            $table->dropForeign(['device_id']);
            $table->dropForeign(['assigned_to']);
        });
    }
};
