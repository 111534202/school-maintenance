<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 devices（設備）資料表。每台設備屬於一個類別、放在一間教室。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：建立 devices 資料表。
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            $table->string('device_code')->unique(); // 設備編號
            $table->string('asset_code')->nullable(); // 資產編號
            // 設備類別（外鍵，指向 device_categories）。
            $table->foreignId('device_category_id')->constrained('device_categories');
            // 品牌（可空）。
            $table->string('brand')->nullable();
            // 型號（可空）。
            $table->string('model')->nullable();
            // 序號（可空）。
            $table->string('serial_number')->nullable();
            $table->date('warranty_until')->nullable(); // 保固期限
            // 所在教室（外鍵，指向 classrooms）。
            $table->foreignId('classroom_id')->constrained('classrooms');
            $table->string('status')->default('normal'); // 設備狀態：normal, repairing, retired...
            // 是否為核心設備：影響空間預約狀態標示，跟劉家芸的模組串接點，異動要先告知她
            $table->boolean('is_core')->default(false);
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    // 還原變更：刪除 devices 資料表。
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('devices');
    }
};
