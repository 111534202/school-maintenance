<?php

namespace Database\Seeders;

use App\Models\DeviceCategory;
use Illuminate\Database\Seeder;

class DeviceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['桌上型電腦', '螢幕', '筆電', '投影機', '交換器', '無線AP', '印表機'];

        foreach ($categories as $name) {
            DeviceCategory::firstOrCreate(['name' => $name]);
        }
    }
}
