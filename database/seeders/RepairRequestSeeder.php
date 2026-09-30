<?php

namespace Database\Seeders;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Models\RepairRequest;
use Illuminate\Database\Seeder;

/**
 * 塞幾筆報修單測試資料，涵蓋 4 種常見情境（依《第四週個人工作計畫》第 6 項
 * 「Demo 情境資料」），並補上不同案件狀態，方便展示看板篩選、驗收流程、
 * 維修紀錄等各種畫面，不用臨時現場輸入：
 * 1. 一般故障（不影響上課）——狀態：新報修
 * 2. 核心設備故障（會影響上課）——狀態：新報修
 * 3. 需要更換備品——狀態：處理中（等劉家芸的 InventoryService 接上後才能真的示範扣庫存）
 * 4. 保養檢查 NG 自動轉報修——透過 CreateRepairRequestFromMaintenanceNg 這個
 *    Action 建立，示範王佑恩的保養模組以後會怎麼呼叫
 * 5. 已派工，尚未開始處理——示範「重新指派」與「維修人員篩選」畫面
 * 6. 待驗收——已經填過一筆維修紀錄，示範「驗收通過/退回」畫面
 * 7. 已結案（且曾經被退回過一次）——示範完整跑過一輪的案件長什麼樣子
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
            // 情境 5：已派工但還沒開始處理，示範「重新指派」跟依維修人員篩選看板。
            [
                'title' => 'D102 教室螢幕不顯示筆電畫面',
                'description' => "接上 HDMI 線後投影幕沒有反應，筆電本身螢幕正常。",
                'impact_level' => 'low',
                'affects_class' => false,
                'status' => 'assigned',
                'device_note' => 'D102 教室投影幕',
                'location' => 'D102',
                'assignee_note' => '王小明',
                'scheduled_at' => now()->addDay(),
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

        // 情境 6：待驗收——已經有一筆維修紀錄，示範「驗收通過/驗收退回」畫面。
        $pendingReview = RepairRequest::updateOrCreate(
            ['title' => 'E201 電腦教室印表機卡紙'],
            [
                'description' => "印表機列印到一半就卡紙，取出卡住的紙後仍持續發生。",
                'impact_level' => 'low',
                'affects_class' => false,
                'status' => 'pending_review',
                'device_note' => 'E201 教室印表機',
                'location' => 'E201',
                'assignee_note' => '劉小華',
            ]
        );
        if ($pendingReview->repairLogs()->doesntExist()) {
            $pendingReview->repairLogs()->create([
                'cause' => '滾輪老化，進紙時容易偏移導致卡紙。',
                'resolution' => '清潔滾輪並重新校正進紙路徑，測試連續列印 10 張正常。',
                'started_at' => now()->subHours(3),
                'ended_at' => now()->subHours(2),
                'total_hours' => 1.0,
            ]);
        }

        // 情境 7：已結案，且中間曾經被驗收退回過一次，示範完整跑過一輪流程的樣子
        // （repair_logs 會有兩筆：第一次沒修好被退回、第二次修好驗收通過）。
        $completed = RepairRequest::updateOrCreate(
            ['title' => 'F103 教室電燈忽明忽暗'],
            [
                'description' => "日光燈管閃爍不穩定，懷疑是老化或安定器問題。",
                'impact_level' => 'medium',
                'affects_class' => false,
                'status' => 'completed',
                'device_note' => 'F103 教室日光燈',
                'location' => 'F103',
                'assignee_note' => '陳大成',
            ]
        );
        if ($completed->repairLogs()->doesntExist()) {
            $completed->repairLogs()->create([
                'cause' => '燈管兩端接觸不良。',
                'resolution' => '重新固定燈管兩端接點。',
                'started_at' => now()->subDays(2)->subHours(2),
                'ended_at' => now()->subDays(2)->subHours(1),
                'total_hours' => 1.0,
            ]);
            $completed->repairLogs()->create([
                'cause' => '重新檢查後發現是安定器老化，非單純接觸不良。',
                'resolution' => '更換整組安定器，測試連續使用 30 分鐘無閃爍。',
                'parts_used_note' => '日光燈安定器 x1',
                'started_at' => now()->subDay()->subHours(2),
                'ended_at' => now()->subDay()->subHours(1),
                'total_hours' => 1.0,
            ]);
        }
    }
}
