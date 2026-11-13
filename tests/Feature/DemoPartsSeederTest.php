<?php

namespace Tests\Feature;

use App\Models\Part;
use Database\Seeders\DemoPartsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 示範零件：20 項、各有一筆期初庫存批次、3 項低於安全庫存；重複執行不會重複新增。 */
class DemoPartsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_twenty_parts_once_with_opening_stock(): void
    {
        $this->seed(DemoPartsSeeder::class);
        $this->seed(DemoPartsSeeder::class);

        $this->assertSame(20, Part::count());
        $this->assertSame(3, Part::all()->filter->isLowStock()->count());
        Part::with('purchases')->get()->each(function (Part $part) {
            $this->assertSame($part->current_stock, (int) $part->purchases->sum('remaining_quantity'), $part->part_code);
        });
    }
}
