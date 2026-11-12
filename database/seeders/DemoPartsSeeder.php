<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

/**
 * 示範用：20 項學校常見的維修零件（電腦教室、投影設備、網路、水電）。
 * 每一項的 current_stock＋unit_price 會自動建立一筆「期初庫存」批次（跟在畫面上新增零件一樣）。
 * 其中 3 項故意低於安全庫存，可以看到「目前低庫存零件」按鈕與低庫存視窗。
 *
 * 執行：php artisan db:seed --class=DemoPartsSeeder
 * 可以重複執行：零件編號已經存在的就跳過，不會重複新增。
 */
class DemoPartsSeeder extends Seeder
{
    public function run(): void
    {
        $parts = [
            // [零件編號, 名稱, 規格, 品牌, 型號, 單位, 目前庫存, 安全庫存, 單價]
            ['MOUSE-01', 'USB 有線滑鼠', '光學 1000DPI', 'Logitech', 'B100', '個', 25, 10, 180],
            ['KB-01', 'USB 有線鍵盤', '中文注音 104 鍵', 'Logitech', 'K120', '個', 18, 8, 350],
            ['MON-01', '液晶螢幕', '24 吋 IPS 1920x1080', 'ASUS', 'VA24EHE', '台', 3, 2, 3290],
            ['RAM-01', '桌機記憶體', 'DDR4-3200 8GB', 'Kingston', 'KVR32N22S8/8', '條', 12, 6, 650],
            ['SSD-01', '固態硬碟', '2.5 吋 SATA 500GB', 'Crucial', 'MX500', '顆', 4, 5, 1390],
            ['PSU-01', '電源供應器', 'ATX 500W 80+ 銅牌', 'Seasonic', 'G12 GC-550', '顆', 3, 2, 1690],
            ['VGA-01', 'VGA 訊號線', '公對公 3 公尺', 'Max+', 'VGA-3M', '條', 9, 4, 150],
            ['HDMI-10', 'HDMI 線', '10 公尺 4K', 'Cable Master', 'HD-10M', '條', 2, 2, 690],
            ['LAMP-01', '投影機燈泡', '適用 EB-X41／X05', 'EPSON', 'ELPLP96', '顆', 1, 2, 2950],
            ['RMT-01', '投影機遙控器', '原廠遙控器', 'EPSON', '2181646', '個', 5, 2, 680],
            ['CAT6-05', '網路線', 'Cat6 5 公尺', 'D-Link', 'NCB-C6UGRYR-5', '條', 30, 10, 130],
            ['RJ45-01', 'RJ45 水晶頭', 'Cat6 100 顆／包', 'Panduit', 'SP688-C', '包', 6, 2, 420],
            ['SW-01', '網路交換器', '8 埠 Gigabit', 'TP-Link', 'TL-SG108', '台', 4, 2, 790],
            ['PWR-01', '延長線', '6 座 3 孔 1.8 公尺 附過載保護', '太星電工', 'OC26318', '條', 15, 5, 320],
            ['TUBE-01', 'LED 燈管', 'T8 4 尺 18W 晝白光', '飛利浦', 'LEDtube T8', '支', 40, 20, 160],
            ['MIC-01', '無線麥克風', 'UHF 手握式', 'MIPRO', 'ACT-32H', '支', 2, 3, 2800],
            ['BAT-01', '鹼性電池', '3 號 AA 4 入', '金頂', 'LR6', '組', 24, 12, 89],
            ['TONER-01', '雷射印表機碳粉匣', '黑色 標準容量', 'HP', 'CF217A', '支', 6, 3, 1650],
            ['FAN-01', '機殼風扇', '12 公分 靜音', 'Cooler Master', 'SickleFlow 120', '個', 10, 4, 220],
            ['PASTE-01', 'CPU 散熱膏', '4 公克', 'Arctic', 'MX-4', '支', 7, 3, 250],
        ];

        foreach ($parts as [$code, $name, $spec, $brand, $model, $unit, $stock, $safety, $price]) {
            if (Part::where('part_code', $code)->exists()) {
                continue;
            }

            Part::create([
                'part_code' => $code,
                'name' => $name,
                'specification' => $spec,
                'brand' => $brand,
                'model' => $model,
                'unit' => $unit,
                'current_stock' => $stock,
                'safety_stock' => $safety,
                'unit_price' => $price,
            ]);
        }
    }
}
