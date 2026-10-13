<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

// 建立四間示範教室，分屬不同部門。可以在「教室主檔」畫面新增或修改，不需要改這個檔案。
// 注意：教室的 reservation_status（設備異常旗標）不是在這裡設定的，而是 DeviceSeeder 建好設備後自動重新計算。
class ClassroomSeeder extends Seeder
{
    // 執行這個 Seeder：建立四間示範教室（php artisan db:seed 時會被呼叫）。
    public function run(): void
    {
        // 先把會用到的部門找出來（DepartmentSeeder 已建立）。
        $cs = Department::where('name', '資訊工程系')->first();
        $ee = Department::where('name', '電機工程系')->first();
        $ge = Department::where('name', '通識教育中心')->first();
        // 示範用的教室管理人：教師測試帳號。
        $manager = User::where('email', 'teacher@school.test')->first();

        // 教室清單：所屬部門、校區、大樓、樓層、教室代碼、名稱、類型、管理人、是否啟用。
        $classrooms = [
            [
                // ?-> 是「找不到部門時不要報錯，改成空值」。
                'department_id' => $cs?->id,
                'campus' => '本校區',
                'building' => '資訊大樓',
                'floor' => '3F',
                // 第 1 間：資訊工程系的電腦教室，有管理人。
                'room_code' => 'IT-301',
                'room_name' => '資訊教室一',
                'room_type' => '電腦教室',
                'manager_id' => $manager?->id,
                'is_active' => true,
            ],
            [
                'department_id' => $cs?->id,
                'campus' => '本校區',
                'building' => '資訊大樓',
                'floor' => '4F',
                // 第 2 間：資訊工程系的第二間電腦教室，有管理人。
                'room_code' => 'IT-401',
                'room_name' => '資訊教室二',
                'room_type' => '電腦教室',
                'manager_id' => $manager?->id,
                'is_active' => true,
            ],
            [
                'department_id' => $ee?->id,
                'campus' => '本校區',
                'building' => '工程大樓',
                'floor' => '2F',
                // 第 3 間：電機工程系的教室，沒有指定管理人（DeviceSeeder 會讓它變成設備異常）。
                'room_code' => 'EE-201',
                'room_name' => '電機實習教室',
                'room_type' => '一般教室',
                'manager_id' => null,
                'is_active' => true,
            ],
            [
                'department_id' => $ge?->id,
                'campus' => '分校區',
                'building' => '研創大樓',
                'floor' => '1F',
                // 第 4 間：通識教育中心在分校區的多功能教室。
                'room_code' => 'IN-101',
                'room_name' => '創客教室',
                'room_type' => '多功能教室',
                'manager_id' => null,
                'is_active' => true,
            ],
        ];

        // 逐一建立。
        foreach ($classrooms as $classroom) {
            // 用教室代碼找，已存在就不動；所以重跑 Seeder 不會產生重複教室。
            Classroom::firstOrCreate(['room_code' => $classroom['room_code']], $classroom);
        }
    }
}
