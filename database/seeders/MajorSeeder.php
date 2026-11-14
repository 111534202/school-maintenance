<?php

namespace Database\Seeders;

use App\Models\Major;
use Illuminate\Database\Seeder;

// 示範用的科系（簡稱、年級數、每年級班數都只是範例，正式資料請在「科系主檔」畫面新增、修改，不需要改這個檔案）。
class MajorSeeder extends Seeder
{
    public function run(): void
    {
        $majors = [
            ['name' => '資訊科', 'major_code' => 'CS', 'abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4],
            ['name' => '電機科', 'major_code' => 'EE', 'abbreviation' => '電', 'grade_count' => 5, 'class_count' => 2],
            ['name' => '護理科', 'major_code' => 'NU', 'abbreviation' => '護', 'grade_count' => 5, 'class_count' => 3],
            ['name' => '資訊管理系（大學部）', 'major_code' => 'IM', 'abbreviation' => '資管', 'grade_count' => 4, 'class_count' => 2],
        ];

        foreach ($majors as $major) {
            // firstOrCreate：用名稱找，已存在就不動，重跑 Seeder 不會產生重複的科系。
            Major::firstOrCreate(['name' => $major['name']], $major + ['is_active' => true]);
        }
    }
}
