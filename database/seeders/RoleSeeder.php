<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => '系統管理員', 'slug' => 'admin'],
            ['name' => '資訊組主管', 'slug' => 'it_manager'],
            ['name' => '維修人員', 'slug' => 'technician'],
            ['name' => '教師／教室管理人', 'slug' => 'teacher'],
            ['name' => '主管／行政人員', 'slug' => 'executive'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
