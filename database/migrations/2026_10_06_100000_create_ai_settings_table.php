<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 第 4 週任務 3：AI 預防保養的可調參數（門檻、最少歷史筆數、去重天數、是否需主管審核）。
     * 用 key/value 結構，之後要新增參數不用再改資料表。
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->comment('參數名稱，例如 risk_threshold');
            $table->string('value', 255)->nullable()->comment('參數值（文字存放，由 AiSetting 轉型）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
