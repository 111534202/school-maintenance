<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 roles（身分／身分組）資料表。五個內建身分由 RoleSeeder 建立；
// 後來新增的說明、是否系統內建、權限清單等欄位在 2026_10_02_172534_add_permissions_to_roles_table 補上。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：建立 roles 資料表。
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            $table->string('name');       // 系統管理員／資訊組主管／維修人員／教師與教室管理人／主管與行政人員
            $table->string('slug')->unique(); // admin, it_manager, technician, teacher, executive
            // 自動維護的建立時間與更新時間。
            $table->timestamps();
        });
    }

    // 還原變更：刪除 roles 資料表。
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('roles');
    }
};
