<?php

namespace Database\Seeders;

use App\Models\MaintenanceItem;
use Illuminate\Database\Seeder;

class MaintenanceItemSeeder extends Seeder
{
    public function run(): void
    {
        MaintenanceItem::factory()->count(8)->create();
    }
}
