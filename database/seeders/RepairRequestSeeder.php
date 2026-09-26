<?php

namespace Database\Seeders;

use App\Models\RepairRequest;
use Illuminate\Database\Seeder;

class RepairRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requests = [
            [
                'title' => 'A101 投影機完全無法開機',
                'description' => "已依知識庫步驟檢查電源線與插座，仍無反應，指示燈不亮。\n上課中無法投影，需要盡快處理。",
                'impact_level' => 'high',
                'affects_class' => true,
                'status' => 'pending',
                'device_note' => 'A101 教室投影機',
                'location' => 'A101',
            ],
            [
                'title' => 'B203 電腦教室其中一台電腦無法連網',
                'description' => "第 3 號機網路孔已換過仍無法連上網路，其他電腦正常。",
                'impact_level' => 'medium',
                'affects_class' => false,
                'status' => 'pending',
                'device_note' => 'B203 電腦教室 3 號機',
                'location' => 'B203',
            ],
        ];

        foreach ($requests as $request) {
            RepairRequest::updateOrCreate(['title' => $request['title']], $request);
        }
    }
}
