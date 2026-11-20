<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 一張保養工單可以包含好幾個保養對象（依《報修與保養流程修改說明》：計畫有好幾台設備／類別時只建一張工單）：
 * 新增 maintenance_order_targets，建立工單時從保養計畫的對象清單複製一份（之後計畫改了也不影響已經建立的工單）。
 * maintenance_orders.device_id／device_category_id 保留，存第一個對象（AI 預防保養工單本來就只有一台設備）。
 * 舊工單把原本的設備／類別搬成唯一的一個對象。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained('maintenance_orders')->cascadeOnDelete()->comment('所屬保養工單');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete()->comment('保養的設備（和設備類別擇一）');
            $table->foreignId('device_category_id')->nullable()->constrained('device_categories')->nullOnDelete()->comment('保養的設備類別（和設備擇一）');
            $table->timestamps();
        });

        DB::table('maintenance_orders')->orderBy('id')->each(function ($order) {
            if ($order->device_id === null && $order->device_category_id === null) {
                return;
            }
            DB::table('maintenance_order_targets')->insert([
                'maintenance_order_id' => $order->id,
                'device_id' => $order->device_id,
                'device_category_id' => $order->device_id === null ? $order->device_category_id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_targets');
    }
};
