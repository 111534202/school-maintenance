<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $room1 = Classroom::where('room_code', 'IT-301')->first();
        $room2 = Classroom::where('room_code', 'IT-401')->first();
        $desktop = DeviceCategory::where('name', '桌上型電腦')->first();
        $projector = DeviceCategory::where('name', '投影機')->first();
        $screen = DeviceCategory::where('name', '螢幕')->first();

        if (!$room1 || !$room2 || !$desktop || !$projector || !$screen) {
            return;
        }

        $devices = [
            [
                'device_code' => 'DEV-0001',
                'asset_code' => 'A-20260001',
                'device_category_id' => $desktop->id,
                'brand' => 'Dell',
                'model' => 'OptiPlex 7010',
                'serial_number' => 'SN-DELL-0001',
                'warranty_until' => '2028-06-30',
                'classroom_id' => $room1->id,
                'status' => 'normal',
                'is_core' => false,
            ],
            [
                'device_code' => 'DEV-0002',
                'asset_code' => 'A-20260002',
                'device_category_id' => $projector->id,
                'brand' => 'Epson',
                'model' => 'EB-2250U',
                'serial_number' => 'SN-EPSON-0001',
                'warranty_until' => '2027-12-31',
                'classroom_id' => $room1->id,
                'status' => 'normal',
                'is_core' => true,
            ],
            [
                'device_code' => 'DEV-0003',
                'asset_code' => 'A-20260003',
                'device_category_id' => $screen->id,
                'brand' => 'BenQ',
                'model' => 'GW2480',
                'serial_number' => 'SN-BENQ-0001',
                'warranty_until' => '2026-12-31',
                'classroom_id' => $room2->id,
                'status' => 'repairing',
                'is_core' => false,
            ],
        ];

        foreach ($devices as $device) {
            Device::firstOrCreate(['device_code' => $device['device_code']], $device);
        }
    }
}
