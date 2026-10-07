<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 departments（部門）資料表。最初只有名稱；代碼、說明、啟用狀態在 2026_10_02_172535_add_master_fields_to_departments_table 補上。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：建立 departments 資料表。
    public function up(): void
    {
        // 待確認事項（規格書第十二節）：departments 關聯細節尚未定案
        // 目前只建立最基本欄位，不擴充正式關聯，待組上確認後再補
        Schema::create('departments', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            // 部門名稱。
            $table->string('name');
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    // 還原變更：刪除 departments 資料表。
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('departments');
    }
};
