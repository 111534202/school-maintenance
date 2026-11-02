<?php

namespace Database\Seeders;

use App\Models\Part;
use App\Services\PartStockService;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * 示範零件：零件編號（PART-1…）由系統自動產生；current_stock＋unit_price 會建立一筆「期初庫存」批次。
     * HDMI 線 2 公尺用三筆採購示範先進先出：之後用料會先扣 9/1 的期初庫存，再扣 10/10、10/12 的批次。
     */
    public function run(): void
    {
        $hdmi = Part::create([
            'name' => 'HDMI 線',
            'specification' => '2公尺',
            'brand' => 'Cable Master',
            'model' => 'HD-2M',
            'current_stock' => 0,   // 庫存由下面三筆採購建立
            'safety_stock' => 3,
        ]);

        Part::create([
            'name' => 'HDMI 線',
            'specification' => '5公尺',
            'brand' => 'Cable Master',
            'model' => 'HD-5M',
            'unit_price' => 280,
            'current_stock' => 4,
            'safety_stock' => 2,
        ]);

        Part::create([
            'name' => 'USB-C 充電線',
            'specification' => '1公尺',
            'brand' => 'Anker',
            'model' => 'A8552',
            'unit_price' => 120,
            'current_stock' => 20,
            'safety_stock' => 5,
        ]);

        Part::create([
            'name' => '網路線',
            'specification' => 'Cat6 3公尺',
            'brand' => 'D-Link',
            'model' => 'NCB-C6UGRYR-3',
            'unit_price' => 100,
            'current_stock' => 2,
            'safety_stock' => 5,
        ]);

        $stock = app(PartStockService::class);
        $stock->receive($hdmi, ['purchase_date' => '2026-09-01', 'vendor_name' => null, 'quantity' => 10, 'unit_price' => 150, 'note' => '期初庫存']);
        $stock->receive($hdmi, ['purchase_date' => '2026-10-10', 'vendor_name' => '光華資訊', 'quantity' => 2, 'unit_price' => 160, 'note' => null]);
        $stock->receive($hdmi, ['purchase_date' => '2026-10-12', 'vendor_name' => '原價屋', 'quantity' => 2, 'unit_price' => 170, 'note' => null]);
    }
}
