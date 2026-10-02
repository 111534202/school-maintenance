<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => '資訊工程系', 'code' => 'CSIE', 'description' => '資訊工程相關系所'],
            ['name' => '電機工程系', 'code' => 'EE', 'description' => '電機工程相關系所'],
            ['name' => '通識教育中心', 'code' => 'GEC', 'description' => '全校通識課程'],
            ['name' => '總務處', 'code' => 'GA', 'description' => '校園設施與設備總務'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(['name' => $department['name']], $department);
        }
    }
}
