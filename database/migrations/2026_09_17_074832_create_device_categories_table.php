<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 device_categories（設備類別）資料表，例如投影機、電腦、冷氣。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：建立 device_categories 資料表。
    public function up(): void
    {
        Schema::create('device_categories', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            $table->string('name'); // 桌上型電腦、螢幕、筆電、投影機、交換器、無線AP、印表機...
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    // 還原變更：刪除 device_categories 資料表。
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('device_categories');
    }
};
