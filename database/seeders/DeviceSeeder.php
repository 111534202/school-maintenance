<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Services\DeviceStatusService;
use Illuminate\Database\Seeder;

// 建立七台示範設備，涵蓋各種狀態（正常、維修中、停用、已淘汰），並刻意讓 EE-201 教室有一台「核心設備維修中」，
// 展示「教室設備異常」的畫面。必須在 ClassroomSeeder、DeviceCategorySeeder 之後執行。
class DeviceSeeder extends Seeder
{
    // 執行這個 Seeder：建立七台示範設備，並重新計算各教室的設備異常標記。
    public function run(): void
    {
        // 先把示範設備要放的四間教室找出來。
        $room1 = Classroom::where('room_code', 'IT-301')->first();
        $room2 = Classroom::where('room_code', 'IT-401')->first();
        $room3 = Classroom::where('room_code', 'EE-201')->first();
        $room4 = Classroom::where('room_code', 'IN-101')->first();
        // 再找出會用到的設備類別。
        $desktop = DeviceCategory::where('name', '桌上型電腦')->first();
        $projector = DeviceCategory::where('name', '投影機')->first();
        $screen = DeviceCategory::where('name', '螢幕')->first();
        $laptop = DeviceCategory::where('name', '筆電')->first();
        $printer = DeviceCategory::where('name', '印表機')->first();

        // 只要有任何一個前置資料不存在（例如沒先跑教室 Seeder），就不建立設備，避免寫入錯誤的資料。
        if (!$room1 || !$room2 || !$room3 || !$room4 || !$desktop || !$projector || !$screen || !$laptop || !$printer) {
            return;
        }

        // 設備清單：設備編號、資產編號、類別、品牌、型號、序號、保固到期日、所在教室、狀態、是否核心設備。
        $devices = [
            [
                // 第 1 台：一般桌上型電腦，正常、非核心設備。
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
                // 第 2 台：投影機，正常、核心設備（故障時會讓教室被標示為異常）。
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
                // 第 3 台：螢幕，維修中、非核心設備（不影響教室異常標記）。
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
                // 第 5 台：筆電，正常、非核心設備。
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
                // 第 6 台：印表機，已停用。
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
                // 第 7 台：桌上型電腦，已淘汰（保固已過期）。
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

        // 逐一建立。
        foreach ($devices as $device) {
            // 用設備編號找，已存在就不動，不存在才建立，避免重複。
            Device::firstOrCreate(['device_code' => $device['device_code']], $device);
        }

        // 種子資料建立後統一重算每間教室的 reservation_status，確保跟核心設備狀態一致
        // 設備都建好後，逐間教室重新計算「設備異常」旗標。
        foreach ([$room1, $room2, $room3, $room4] as $room) {
            // fresh() 重新從資料庫讀一次教室，再依核心設備狀態算出該教室是 normal 還是 abnormal。
            DeviceStatusService::syncClassroom($room->fresh());
        }
    }
}
