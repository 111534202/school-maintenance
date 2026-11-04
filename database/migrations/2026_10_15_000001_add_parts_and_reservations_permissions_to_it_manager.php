<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
// 新增「零件庫存」（parts.manage）與「空間預約」（reservations.manage）兩個權限後，「已經建好的資料庫」裡的
// 設備管理員（it_manager）還沒有這兩項（種子資料不會覆蓋已存在的身分），升級後他們會突然進不去零件庫存與預約管理，
// 所以這支 migration 把預設值「補進去」。只做「新增」不做「覆蓋」：其他權限不動，重跑也不會產生重複項目。
return new class extends Migration
{
    // 要補進去的權限（寫死，不受之後改動 PermissionCatalog 影響）。admin 永遠全開，不用補。
    private const CODES = ['parts.manage', 'reservations.manage'];

    public function up(): void
    {
        $role = DB::table('roles')->where('slug', 'it_manager')->first();
        if ($role === null) {
            return;   // 這個資料庫沒有設備管理員身分就略過
        }

        // 原本的權限（JSON 文字 → 陣列；空值當成空陣列），再合併新權限並去除重複。
        $current = json_decode($role->permissions ?? '[]', true) ?: [];
        $merged = array_values(array_unique(array_merge($current, self::CODES)));

        DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($merged)]);
    }

    // 還原：把這兩個權限從設備管理員拿掉（其他權限不動）。
    public function down(): void
    {
        $role = DB::table('roles')->where('slug', 'it_manager')->first();
        if ($role === null) {
            return;
        }

        $current = json_decode($role->permissions ?? '[]', true) ?: [];
        $kept = array_values(array_diff($current, self::CODES));

        DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($kept)]);
    }
};
