<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 保養計畫改回「一個計畫一個保養對象」（劉家芸確認）：拿掉計畫、工單的多對象清單。
 * 計畫與工單本身的 device_id／device_category_id 一直都存著第一個對象，所以不用搬資料；多出來的對象直接捨棄。
 * 建表的兩個 migration 已經推上去了（可能有人跑過），所以保留檔案、在這裡用 dropIfExists 拿掉兩張表；down() 只重建計畫對象表。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('maintenance_order_targets');
        Schema::dropIfExists('maintenance_plan_targets');
    }

    public function down(): void
    {
        Schema::create('maintenance_plan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete()->comment('所屬保養計畫');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete()->comment('保養的設備（和設備類別擇一）');
            $table->foreignId('device_category_id')->nullable()->constrained('device_categories')->nullOnDelete()->comment('保養的設備類別（和設備擇一）');
            $table->timestamps();
        });

        DB::table('maintenance_plans')->orderBy('id')->each(function ($plan) {
            if ($plan->device_id === null && $plan->device_category_id === null) {
                return;
            }
            DB::table('maintenance_plan_targets')->insert([
                'maintenance_plan_id' => $plan->id,
                'device_id' => $plan->device_id,
                'device_category_id' => $plan->device_id === null ? $plan->device_category_id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
