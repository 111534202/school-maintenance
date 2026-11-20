<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 保養計畫可以有好幾個保養對象（設備或設備類別，每一列擇一）：新增 maintenance_plan_targets。
 * 舊計畫的 device_id／device_category_id 搬成第一列對象；maintenance_plans 上的兩個欄位保留，存第一個對象（舊的程式與報表照常可讀）。
 * 排程或手動建立工單時，每個對象各建一張工單（工單本來就是一台設備或一個類別）。
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_targets');
    }
};
