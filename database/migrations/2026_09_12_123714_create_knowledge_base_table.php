<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 knowledge_base（自助排除知識庫文章）資料表。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 表名依規格文件固定為 knowledge_base（單數），不是 Laravel 預設的複數命名。
        Schema::create('knowledge_base', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            // 文章標題。
            $table->string('title');
            // 分類（文字）。目前只是文字欄位，沒有關聯到設備類別表。
            $table->string('category')->nullable()->comment('待確認：是否改關聯 device_categories，目前該表未建立，先用文字欄位');
            // 常見故障現象（長文字）。
            $table->text('symptom')->comment('常見故障現象/問題描述');
            // 自助排除步驟（長文字）。
            $table->text('solution')->comment('自助排除步驟');
            // 是否上架給使用者看；預設上架。
            $table->boolean('is_published')->default(true);
            // 待確認：users 表由林政寬本週建立中，尚未確定可否安全關聯，先用不加外鍵的欄位記錄建立者
            // 建立者的用戶編號。目前沒有外鍵約束（只是一個數字欄位），users 表現在已經穩定，之後可補上外鍵。
            $table->unsignedBigInteger('created_by')->nullable()->comment('待補 FK -> users.id，users 表穩定後再加');
            // 自動維護的建立時間 created_at 與更新時間 updated_at。
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('knowledge_base');
    }
};
