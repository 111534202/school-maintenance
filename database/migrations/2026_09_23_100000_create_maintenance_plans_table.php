<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 保養計畫（maintenance_plans）。
 *
 * device_id 暫不加外鍵約束：devices 表由林政寬主責，目前這個獨立開發用的
 * 專案裡還沒有那張表（等接回同學的共用 Repository 後，這裡要補上
 * ->foreignId('device_id')->constrained('devices')，並移除這則註解）。
 * device_category 是「對應設備或設備類別」原規格未定案前的工程實作欄位。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('保養計畫名稱');
            $table->unsignedBigInteger('device_id')->nullable()
                ->comment('對應設備，待接回共用 Repo 後補上 devices 外鍵');
            $table->string('device_category', 100)->nullable()
                ->comment('工程實作欄位：對應設備類別（與 device_id 擇一使用，暫定）');
            $table->unsignedInteger('cycle_days')->comment('保養週期（天）');
            $table->date('start_date')->comment('計畫起始日');
            $table->date('next_due_date')->nullable()->comment('下次到期日，依週期自動計算');
            $table->boolean('is_active')->default(true)->comment('啟用/停用，取代刪除');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
