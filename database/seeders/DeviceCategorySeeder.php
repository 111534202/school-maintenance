<?php

namespace Database\Seeders;

use App\Models\DeviceCategory;
use Illuminate\Database\Seeder;

// 建立示範用的七種設備類別。之後可以在「設備類別」主檔畫面新增或刪除。
class DeviceCategorySeeder extends Seeder
{
    // 執行這個 Seeder：建立七種示範設備類別。
    public function run(): void
    {
        // 類別名稱清單。
        $categories = ['桌上型電腦', '螢幕', '筆電', '投影機', '交換器', '無線AP', '印表機'];

        // 逐一建立。
        foreach ($categories as $name) {
            // 用名稱找，已存在就不動，不存在才建立，避免重複。
            DeviceCategory::firstOrCreate(['name' => $name]);
        }
    }
}
