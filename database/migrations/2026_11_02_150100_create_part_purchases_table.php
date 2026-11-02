<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 零件採購紀錄（每一筆就是一個進貨批次）。
 *
 * 成本採「先進先出」：維修或保養用掉零件時，先扣最早買進、還有剩的批次，
 * 用料明細記下那一批的單價。remaining_quantity 是這一批還沒用掉的數量。
 *
 * 原本 parts.unit_price（手動輸入的單價）改由批次單價取代：
 * 既有零件的目前庫存轉成一筆「期初庫存」批次（單價沿用原本的 unit_price），再移除 parts.unit_price。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->date('purchase_date');                       // 採購（入庫）日期
            $table->string('vendor_name')->nullable();           // 廠商名稱；期初庫存沒有廠商
            $table->unsignedInteger('quantity');                 // 購買數量
            $table->unsignedInteger('unit_price');               // 單價（總價＝數量×單價，不另存）
            $table->unsignedInteger('remaining_quantity');       // 這一批還沒用掉的數量
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['part_id', 'purchase_date']);
        });

        $now = now();
        foreach (DB::table('parts')->where('current_stock', '>', 0)->orderBy('id')->get() as $part) {
            DB::table('part_purchases')->insert([
                'part_id' => $part->id,
                'purchase_date' => substr((string) ($part->created_at ?? $now), 0, 10),
                'vendor_name' => null,
                'quantity' => $part->current_stock,
                'unit_price' => $part->unit_price,
                'remaining_quantity' => $part->current_stock,
                'note' => '期初庫存',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->unsignedInteger('unit_price')->default(0);
        });

        // 退回時以最近一批採購的單價當作零件單價。
        foreach (DB::table('parts')->pluck('id') as $partId) {
            $latest = DB::table('part_purchases')->where('part_id', $partId)
                ->orderByDesc('purchase_date')->orderByDesc('id')->value('unit_price');
            DB::table('parts')->where('id', $partId)->update(['unit_price' => $latest ?? 0]);
        }

        Schema::dropIfExists('part_purchases');
    }
};
