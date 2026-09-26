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
        ];

        foreach ($entries as $entry) {
            KnowledgeBase::updateOrCreate(['title' => $entry['title']], $entry);
        }
    }
}
