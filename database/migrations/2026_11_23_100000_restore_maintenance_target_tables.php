<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 保養計畫又改回「可以有好幾個保養對象」（劉家芸確認，11/23）：把 2026_11_21_130000 拿掉的兩張表建回來，
 * 依計畫、工單本身的 device_id／device_category_id 各補一筆對象（就是原本那一個）。
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['maintenance_plan_targets' => ['maintenance_plan_id', 'maintenance_plans', '所屬保養計畫'],
            'maintenance_order_targets' => ['maintenance_order_id', 'maintenance_orders', '所屬保養工單']] as $table => [$key, $parent, $comment]) {
            if (Schema::hasTable($table)) {
                continue;
            }
            Schema::create($table, function (Blueprint $t) use ($key, $parent, $comment) {
                $t->id();
                $t->foreignId($key)->constrained($parent)->cascadeOnDelete()->comment($comment);
                $t->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete()->comment('保養的設備（和設備類別擇一）');
                $t->foreignId('device_category_id')->nullable()->constrained('device_categories')->nullOnDelete()->comment('保養的設備類別（和設備擇一）');
                $t->timestamps();
            });

            DB::table($parent)->orderBy('id')->each(function ($row) use ($table, $key) {
                if ($row->device_id === null && $row->device_category_id === null) {
                    return;
                }
                DB::table($table)->insert([
                    $key => $row->id,
                    'device_id' => $row->device_id,
                    'device_category_id' => $row->device_id === null ? $row->device_category_id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_targets');
        Schema::dropIfExists('maintenance_plan_targets');
    }
};
