<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 保養計畫 ↔ 保養項目 的多對多樞紐表，支援一個計畫對應多個保養項目。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')
                ->constrained('maintenance_plans')
                ->cascadeOnDelete();
            $table->foreignId('maintenance_item_id')
                ->constrained('maintenance_items')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['maintenance_plan_id', 'maintenance_item_id'], 'plan_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_items');
    }
};
