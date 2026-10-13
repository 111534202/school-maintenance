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
        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_plan_id')
                ->nullable()
                ->comment('保養工單來源計畫')
                ->constrained('maintenance_plans')
                ->nullOnDelete();

            $table->unsignedBigInteger('device_id')->nullable()
                ->comment('對應設備，待接回共用 Repo 後補上 devices 外鍵');
            $table->string('device_category', 100)->nullable()
                ->comment('工程實作欄位：建立工單當下由來源計畫快照設備類別');

            $table->string('source', 20)->default('periodic')
                ->comment('工單來源：periodic 定期 / ai AI 辨識，本週僅產生 periodic，AI 判斷由後續任務接手');

            $table->string('status', 20)->default('pending')
                ->comment('基本狀態：pending 待處理 / in_progress 進行中 / completed 已完成，完整狀態流程由保養結果模組（第2週）接手');

            $table->date('scheduled_date')->nullable()
                ->comment('排定保養日期，建立工單時預設帶入來源計畫的下次到期日');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_orders');
    }
};
