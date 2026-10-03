<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 attachments（附件）資料表，報修單與維修紀錄共用（多型關聯）。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 依《第 2 週個人工作計畫》第 1 項「附件上傳第一版」。用多型關聯讓報修單
        // （repair_requests）跟維修紀錄（repair_logs，維修前後照片）共用同一張表，
        // 不用各自建一張，符合任務指示的「attachments 共用表」。
        Schema::create('attachments', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            $table->morphs('attachable'); // attachable_type + attachable_id
            // 檔案存放在硬碟上的相對路徑。
            $table->string('disk_path');
            // 使用者上傳時的原始檔名。
            $table->string('original_name');
            // 檔案類型，例如 image/jpeg。
            $table->string('mime_type');
            // 檔案大小（位元組）。
            $table->unsignedBigInteger('size_bytes');
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('attachments');
    }
};
