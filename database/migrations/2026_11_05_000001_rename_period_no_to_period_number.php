<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 統一「第幾個」這類序號欄位的寫法：
//   classroom_reservation_slots.period_no → period_number
// 原因：序號一律寫完整單字 <名詞>_number（設備序號早就是 devices.serial_number），
// 不用 _no 縮寫；_no 在規格書舊欄位（room_no、asset_no、serial_no）已全部改掉，不再保留這種寫法。
// 資料原封不動，只改名稱。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('classroom_reservation_slots', 'period_no')) {
            Schema::table('classroom_reservation_slots', fn (Blueprint $table) => $table->renameColumn('period_no', 'period_number'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classroom_reservation_slots', 'period_number')) {
            Schema::table('classroom_reservation_slots', fn (Blueprint $table) => $table->renameColumn('period_number', 'period_no'));
        }
    }
};
