<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $cs = Department::where('name', '資訊工程系')->first();
        $ee = Department::where('name', '電機工程系')->first();
        $manager = User::where('email', 'teacher@school.test')->first();

        $classrooms = [
            [
                'department_id' => $cs?->id,
                'campus' => '本校區',
                'building' => '資訊大樓',
                'floor' => '3F',
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
                'room_code' => 'EE-201',
                'room_name' => '電機實習教室',
                'room_type' => '一般教室',
                'manager_id' => null,
                'is_active' => true,
            ],
        ];

        foreach ($classrooms as $classroom) {
            Classroom::firstOrCreate(['room_code' => $classroom['room_code']], $classroom);
        }
    }
}
