<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Part::create([
            'name' => 'HDMI 線',
            'specification' => '2公尺',
            'unit_price' => 150,
            'current_stock' => 10,
            'safety_stock' => 3,
        ]);

        Part::create([
            'name' => 'USB-C 充電線',
            'specification' => '1公尺',
            'unit_price' => 120,
            'current_stock' => 20,
            'safety_stock' => 5,
        ]);

        Part::create([
            'name' => '網路線',
            'specification' => 'Cat6 3公尺',
            'unit_price' => 100,
            'current_stock' => 2,
            'safety_stock' => 5,
        ]);
    }
}