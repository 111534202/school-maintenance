<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 audit_logs（操作紀錄）資料表：誰、何時、對哪筆資料做了什麼。寫入一律透過 App\Services\AuditLogger。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // 操作人，帳號被停用/軟刪除也不影響歷史記錄
            $table->string('action'); // 例如：login, created, updated, status_changed
            $table->string('loggable_type')->nullable(); // 多型關聯：被操作的模型類別
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->json('changes')->nullable(); // 變更內容，例如 {"from":"normal","to":"repairing"}
            $table->string('description')->nullable(); // 人類可讀描述
            // 自動維護的建立時間與更新時間（紀錄頁顯示的時間就是 created_at）。
            $table->timestamps();

            // 替「對象類型 + 對象編號」建索引，之後查某筆資料的歷史紀錄會比較快。
            $table->index(['loggable_type', 'loggable_id']);
            // 替事件代碼建索引，篩選事件時比較快。
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('audit_logs');
    }
};
