<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

// 建立示範用的四個部門。之後可以在「部門主檔」畫面新增、修改、停用，不需要改這個檔案。
class DepartmentSeeder extends Seeder
{
    // 執行這個 Seeder：建立四個示範部門。
    public function run(): void
    {
        // 部門清單：name 名稱、code 代碼、description 說明。
        $departments = [
            ['name' => '資訊工程系', 'code' => 'CSIE', 'description' => '資訊工程相關系所'],
            ['name' => '電機工程系', 'code' => 'EE', 'description' => '電機工程相關系所'],
            ['name' => '通識教育中心', 'code' => 'GEC', 'description' => '全校通識課程'],
            ['name' => '總務處', 'code' => 'GA', 'description' => '校園設施與設備總務'],
        ];

        // 逐一建立。
        foreach ($departments as $department) {
            // firstOrCreate：用名稱找，已存在就不動，不存在才建立；所以重跑 Seeder 不會產生重複的部門。
            Department::firstOrCreate(['name' => $department['name']], $department);
        }
    }
}
