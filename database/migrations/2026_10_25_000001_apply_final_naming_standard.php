<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 依 docs/資料庫命名規範與資料字典.md 的「定案名稱」把剩下不符合規則的名稱一次改完（資料原封不動，只改名稱）：
//   欄位：
//     classrooms.room_code        → classroom_code      業務代碼一律叫 <實體>_code
//     classrooms.room_name        → name                稱呼一律叫 name
//     classrooms.room_type        → type                屬性不重複實體名稱
//     devices.warranty_until      → warranty_end_date   日期欄位用 _date 結尾
//     repair_requests.affects_class → is_class_affected 布林欄位用 is_ 開頭
//     attachments.original_name   → file_name           （對齊規格書 attachments.file_name）
//     attachments.disk_path       → file_path           （對齊規格書 attachments.file_path）
//     roles.slug                  → role_code           業務代碼一律叫 <實體>_code
//     users.username              → account             （對齊規格書 users.account）
//   資料值：
//     audit_logs.action 的 login → logged_in、logout → logged_out（動作值一律用過去式）
return new class extends Migration
{
    private const COLUMNS = [
        'classrooms' => ['room_code' => 'classroom_code', 'room_name' => 'name', 'room_type' => 'type'],
        'devices' => ['warranty_until' => 'warranty_end_date'],
        'repair_requests' => ['affects_class' => 'is_class_affected'],
        'attachments' => ['original_name' => 'file_name', 'disk_path' => 'file_path'],
        'roles' => ['slug' => 'role_code'],
        'users' => ['username' => 'account'],
    ];

    private const ACTIONS = ['login' => 'logged_in', 'logout' => 'logged_out'];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $renames) {
            foreach ($renames as $from => $to) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
            }
        }

        foreach (self::ACTIONS as $from => $to) {
            DB::table('audit_logs')->where('action', $from)->update(['action' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::ACTIONS as $from => $to) {
            DB::table('audit_logs')->where('action', $to)->update(['action' => $from]);
        }

        foreach (array_reverse(self::COLUMNS, true) as $table => $renames) {
            foreach ($renames as $from => $to) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($to, $from));
            }
        }
    }
};
