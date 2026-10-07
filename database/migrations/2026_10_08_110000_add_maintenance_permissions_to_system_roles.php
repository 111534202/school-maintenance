<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
// 保養模組新增四個權限後，「已經建好的資料庫」裡的內建身分還沒有這些權限（種子資料用 firstOrCreate，
// 不會覆蓋已存在的身分），所以這支 migration 把預設值「補進去」，不然升級後維修人員、主管會突然看不到保養功能。
// 只做「新增」不做「覆蓋」：管理員在畫面上調整過的其他權限不會被動到；重跑也不會產生重複項目。
return new class extends Migration
{
    // 內建身分 => 要補進去的保養權限（跟 PermissionCatalog::DEFAULTS 一致；這裡寫死，
    // 因為 migration 不該受之後改動 PermissionCatalog 的影響）。admin 永遠全開，不用補；teacher 預設沒有。
    private const GRANTS = [
        'it_manager' => ['maintenance.view', 'maintenance.manage', 'maintenance.report', 'ai-maintenance.manage'],
        'technician' => ['maintenance.view', 'maintenance.report'],
        'executive' => ['maintenance.view'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $slug => $codes) {
            $role = DB::table('roles')->where('slug', $slug)->first();
            if ($role === null) {
                continue;   // 這個資料庫沒有這個身分就略過
            }

            // 原本的權限（JSON 文字 → 陣列；空值當成空陣列），再合併新權限並去除重複。
            $current = json_decode($role->permissions ?? '[]', true) ?: [];
            $merged = array_values(array_unique(array_merge($current, $codes)));

            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($merged)]);
        }
    }

    // 還原：把這四個權限從內建身分拿掉（其他權限不動）。
    public function down(): void
    {
        $all = ['maintenance.view', 'maintenance.manage', 'maintenance.report', 'ai-maintenance.manage'];

        foreach (array_keys(self::GRANTS) as $slug) {
            $role = DB::table('roles')->where('slug', $slug)->first();
            if ($role === null) {
                continue;
            }

            $current = json_decode($role->permissions ?? '[]', true) ?: [];
            $kept = array_values(array_diff($current, $all));

            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($kept)]);
        }
    }
};
