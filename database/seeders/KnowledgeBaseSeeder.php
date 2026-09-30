<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use Illuminate\Database\Seeder;

/** 塞幾筆知識庫測試資料，讓「自助排除知識庫」頁面一打開就有內容可以看。 */
class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'title' => '投影機無法開機',
                'category' => '投影機',
                'symptom' => '按下電源鍵後指示燈不亮，畫面無任何顯示。',
                'solution' => "1. 確認電源線與插座是否確實插好。\n2. 檢查教室電源總開關是否跳電。\n3. 更換另一組插座測試。\n4. 若仍無反應，請直接送出報修單並在描述中註明已完成上述檢查。",
                'is_published' => true,
            ],
            [
                'title' => '教室電腦無法連上網路',
                'category' => '電腦',
                'symptom' => '電腦畫面右下角網路圖示顯示紅色叉叉，無法開啟任何網頁。',
                'solution' => "1. 確認網路線兩端是否確實插緊。\n2. 重新啟動電腦。\n3. 嘗試切換到教室內其他網路孔測試。\n4. 若同教室其他設備也無法上網，可能是網路交換器問題，請直接報修。",
                'is_published' => true,
            ],
            [
                'title' => '投影機畫面模糊或色偏',
                'category' => '投影機',
                'symptom' => '畫面可以顯示，但文字模糊不清或顏色明顯偏差。',
                'solution' => "1. 使用遙控器或機身按鍵進行自動對焦。\n2. 檢查投影機濾網是否需要清潔（可能影響亮度與清晰度）。\n3. 確認訊號線（HDMI/VGA）是否鬆脫。\n4. 若清潔與重新連接後仍無改善，請報修並註明是否有異味或過熱現象。",
                'is_published' => false,
            ],
            // 以下為第五週補充的展示資料，讓知識庫列表更豐富，涵蓋更多常見設備類型。
            [
                'title' => '教室冷氣沒有冷房效果',
                'category' => '空調',
                'symptom' => '冷氣出風正常，但吹出來的風不冷，室溫沒有下降。',
                'solution' => "1. 確認遙控器設定溫度是否低於室溫。\n2. 檢查濾網是否堵塞，堵塞會嚴重影響冷房效果。\n3. 確認室外機周圍沒有雜物擋住排熱。\n4. 若上述都正常但仍不冷，可能是冷媒問題，請直接報修。",
                'is_published' => true,
            ],
            [
                'title' => '無線網路（Wi-Fi）連不上或訊號很弱',
                'category' => '網路',
                'symptom' => '手機/筆電搜尋不到教室 Wi-Fi，或訊號顯示很弱、常常斷線。',
                'solution' => "1. 確認裝置的飛航模式沒有開啟。\n2. 忘記該 Wi-Fi 後重新搜尋並連線。\n3. 移動到靠近無線 AP（通常裝在天花板）的位置測試訊號。\n4. 若同教室其他人也連不上，可能是 AP 故障，請直接報修並註明是「全教室都連不上」還是「只有自己連不上」。",
                'is_published' => true,
            ],
            [
                'title' => '電腦開機後畫面卡在黑畫面',
                'category' => '電腦',
                'symptom' => '按下電源鍵風扇有轉、指示燈有亮，但螢幕一直是黑的，沒有任何畫面。',
                'solution' => "1. 確認螢幕本身有沒有開機、訊號線是否插好。\n2. 嘗試更換螢幕訊號線的另一個孔位（例如從 HDMI 換成 VGA）。\n3. 長按電源鍵 10 秒強制關機，等 10 秒後重新開機。\n4. 若仍是黑畫面，請直接報修，並註明螢幕是否顯示「無訊號」字樣。",
                'is_published' => true,
            ],
        ];

        foreach ($entries as $entry) {
            KnowledgeBase::updateOrCreate(['title' => $entry['title']], $entry);
        }
    }
}
