<?php

namespace Database\Seeders;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\RepairRequest;
use Illuminate\Database\Seeder;

/**
 * 塞幾筆報修單測試資料。依《第四週個人工作計畫》第 6 項「Demo 情境資料」，
 * 這裡涵蓋 4 種常見情境，方便展示的時候不用臨時現場輸入：
 * 1. 一般故障（不影響上課）
 * 2. 核心設備故障（會影響上課）
 * 3. 需要更換備品（等劉家芸的 InventoryService 接上後才能真的示範扣庫存）
 * 4. 保養檢查 NG 自動轉報修（透過 CreateRepairRequestFromMaintenanceNg 這個
 *    Action 建立，示範王佑恩的保養模組以後會怎麼呼叫）
 */
class RepairRequestSeeder extends Seeder
{
    public function run(): void
    {
        // 情境 1：一般故障，不影響上課。
        $requests = [
            [
                'title' => 'B203 電腦教室其中一台電腦無法連網',
                'description' => "第 3 號機網路孔已換過仍無法連上網路，其他電腦正常。",
                'impact_level' => 'medium',
                'affects_class' => false,
                'status' => 'pending',
                'device_note' => 'B203 電腦教室 3 號機',
                'location' => 'B203',
            ],
            // 情境 2：核心設備故障，正在影響上課。
            [
                'title' => 'A101 投影機完全無法開機',
                'description' => "已依知識庫步驟檢查電源線與插座，仍無反應，指示燈不亮。\n上課中無法投影，需要盡快處理。",
                'impact_level' => 'high',
                'affects_class' => true,
                'status' => 'pending',
                'device_note' => 'A101 教室投影機（核心設備）',
                'location' => 'A101',
            ],
            // 情境 3：需要更換備品。備品選擇串接要等劉家芸的 InventoryService
            // 確定介面後才能真正扣庫存（見 docs/待確認/Week3_劉家芸.md），
            // 這裡先示範「維修人員填單時發現需要換零件」的案件長什麼樣子。
            [
                'title' => 'C305 無線 AP 訊號微弱',
                'description' => "現場檢查疑似天線老化，可能需要更換無線 AP 的天線或整台更換。",
                'impact_level' => 'medium',
                'affects_class' => false,
                'status' => 'in_progress',
                'device_note' => 'C305 無線 AP',
                'location' => 'C305',
                'assignee_note' => '陳大成',
            ],
        ];

        foreach ($requests as $request) {
            RepairRequest::updateOrCreate(['title' => $request['title']], $request);
        }

        // 情境 4：保養檢查 NG，自動轉成報修單（不是直接塞資料，而是真的呼叫
        // CreateRepairRequestFromMaintenanceNg，這樣可以順便驗證這個 Action 本身沒問題）。
        if (! RepairRequest::where('title', '投影機保養檢查 NG：燈泡亮度不足')->exists()) {
            app(CreateRepairRequestFromMaintenanceNg::class)->execute(
                sourceLabel: 'maintenance_result:demo-1',
                title: '投影機保養檢查 NG：燈泡亮度不足',
                description: '定期保養檢查時發現燈泡亮度低於標準值，建議更換。',
                deviceNote: 'B203 教室投影機',
                impactLevel: 'medium',
            );
        }
    }
}
