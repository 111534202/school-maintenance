<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用料明細記錄「用的是哪一批採購」：
 * - repair_part_usages 補 part_purchase_id（維修用料）。
 * - 新增 maintenance_part_usages（保養用料），欄位與維修用料相同，只是掛在保養結果底下。
 * 一次用料如果跨了兩個批次（先進先出），會拆成兩列，各自記那一批的單價。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_part_usages', function (Blueprint $table) {
            $table->foreignId('part_purchase_id')->nullable()->after('part_id')
                ->constrained('part_purchases')->nullOnDelete();
        });

        Schema::create('maintenance_part_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_result_id')->constrained('maintenance_results')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->foreignId('part_purchase_id')->nullable()->constrained('part_purchases')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_part_usages');

        Schema::table('repair_part_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_purchase_id');
        });
    }
};
