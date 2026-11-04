<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 欄位命名統一（規則見 docs/資料庫命名規範與資料字典.md）：
//   - 給人看的「業務代碼」一律叫 <實體>_code（devices.device_code、classrooms.room_code），所以 departments.code 改成 department_code。
//   - 操作人員的「備註」一律用單數 note（decision_note、device_note 也是這個寫法），所以 maintenance_results.notes 改成 note。
// 只改名稱，資料原封不動（唯一索引也會跟著改名）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->renameColumn('code', 'department_code'));
        Schema::table('maintenance_results', fn (Blueprint $table) => $table->renameColumn('notes', 'note'));
    }

    public function down(): void
    {
        Schema::table('maintenance_results', fn (Blueprint $table) => $table->renameColumn('note', 'notes'));
        Schema::table('departments', fn (Blueprint $table) => $table->renameColumn('department_code', 'code'));
    }
};
