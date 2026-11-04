<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 第三正規化（3NF）：不要存「可以由別的欄位推出來」的資料。
// 保養計畫與保養工單針對的是「一台設備」（device_id）或「一類設備」（device_category_id），二選一：
// 已經指定了某一台設備，它的類別就能由 devices.device_category_id 推出來，再存一份類別會產生
// 「設備改了類別、計畫還留著舊類別」的不一致。這一支把有設備的資料列的 device_category_id 清成空值；
// 之後由 MaintenancePlan／MaintenanceOrder 的 saving 事件自動維持這個規則，畫面用 categoryName() 顯示類別。
return new class extends Migration
{
    public function up(): void
    {
        foreach (['maintenance_plans', 'maintenance_orders'] as $table) {
            DB::table($table)->whereNotNull('device_id')->whereNotNull('device_category_id')->update(['device_category_id' => null]);
        }
    }

    // 還原：把設備的類別抄回去（回到整理前「兩邊都存」的狀態）。
    public function down(): void
    {
        foreach (['maintenance_plans', 'maintenance_orders'] as $table) {
            foreach (DB::table($table)->whereNotNull('device_id')->whereNull('device_category_id')->get(['id', 'device_id']) as $row) {
                $categoryId = DB::table('devices')->where('id', $row->device_id)->value('device_category_id');
                DB::table($table)->where('id', $row->id)->update(['device_category_id' => $categoryId]);
            }
        }
    }
};
