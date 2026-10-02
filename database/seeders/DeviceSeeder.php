<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Services\DeviceStatusService;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $room1 = Classroom::where('room_code', 'IT-301')->first();
        $room2 = Classroom::where('room_code', 'IT-401')->first();
        $room3 = Classroom::where('room_code', 'EE-201')->first();
        $room4 = Classroom::where('room_code', 'IN-101')->first();
        $desktop = DeviceCategory::where('name', '桌上型電腦')->first();
        $projector = DeviceCategory::where('name', '投影機')->first();
        $screen = DeviceCategory::where('name', '螢幕')->first();
        $laptop = DeviceCategory::where('name', '筆電')->first();
        $printer = DeviceCategory::where('name', '印表機')->first();

        if (!$room1 || !$room2 || !$room3 || !$room4 || !$desktop || !$projector || !$screen || !$laptop || !$printer) {
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
            [
                // 核心設備故障示範：讓 EE-201 的 reservation_status 種子後即為 abnormal，
                // 方便劉家芸的預約模組直接測試「核心設備異常時要顯示異常」的畫面。
                'device_code' => 'DEV-0004',
                'asset_code' => 'A-20260004',
                'device_category_id' => $projector->id,
                'brand' => 'NEC',
                'model' => 'NP-PA653U',
                'serial_number' => 'SN-NEC-0001',
                'warranty_until' => '2027-06-30',
                'classroom_id' => $room3->id,
                'status' => 'repairing',
                'is_core' => true,
            ],
            [
                'device_code' => 'DEV-0005',
                'asset_code' => 'A-20260005',
                'device_category_id' => $laptop->id,
                'brand' => 'Lenovo',
                'model' => 'ThinkPad E14',
                'serial_number' => 'SN-LENOVO-0001',
                'warranty_until' => '2029-01-31',
                'classroom_id' => $room4->id,
                'status' => 'normal',
                'is_core' => false,
            ],
            [
                'device_code' => 'DEV-0006',
                'asset_code' => 'A-20260006',
                'device_category_id' => $printer->id,
                'brand' => 'HP',
                'model' => 'LaserJet Pro M404',
                'serial_number' => 'SN-HP-0001',
                'warranty_until' => '2025-12-31',
                'classroom_id' => $room4->id,
                'status' => 'disabled',
                'is_core' => false,
            ],
            [
                'device_code' => 'DEV-0007',
                'asset_code' => 'A-20260007',
                'device_category_id' => $desktop->id,
                'brand' => 'Dell',
                'model' => 'OptiPlex 3000',
                'serial_number' => 'SN-DELL-0002',
                'warranty_until' => '2024-06-30',
                'classroom_id' => $room2->id,
                'status' => 'retired',
                'is_core' => false,
            ],
        ];

        foreach ($devices as $device) {
            Device::firstOrCreate(['device_code' => $device['device_code']], $device);
        }

        // 種子資料建立後統一重算每間教室的 reservation_status，確保跟核心設備狀態一致
        foreach ([$room1, $room2, $room3, $room4] as $room) {
            DeviceStatusService::syncClassroom($room->fresh());
        }
    }
}
