<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repair_part_usage', function (Blueprint $table) {
            $table->id();

            // 對應維修紀錄
            $table->foreignId('repair_log_id')
                ->constrained('repair_logs')
                ->cascadeOnDelete();

            // 對應使用的零件
            $table->foreignId('part_id')
                ->constrained('parts')
                ->restrictOnDelete();

            // 使用數量，必須大於 0
            $table->unsignedInteger('quantity');

            $table->timestamps();

            // 同一筆維修紀錄、同一種零件只保留一筆明細
            $table->unique(
                ['repair_log_id', 'part_id'],
                'repair_part_usage_log_part_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_part_usage');
    }
};
