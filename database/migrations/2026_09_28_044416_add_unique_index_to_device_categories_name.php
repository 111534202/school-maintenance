<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 替 device_categories.name 加上「唯一索引」：資料庫層級保證類別名稱不會重複。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_categories', function (Blueprint $table) {
            $table->unique('name'); // Controller 已檢查唯一性，補上 DB 層級保證
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_categories', function (Blueprint $table) {
            // down()：還原時移除唯一索引。
            $table->dropUnique(['name']);
        });
    }
};
