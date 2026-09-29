<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 保養項目（maintenance_items）
 *
 * 對應《王佑恩－個人任務分工與執行指示》3.1：本表由本模組主責建立與維護。
 * 下列欄位原規格未完全列明實際型別/選項，暫以最小可運作設計實作，
 * 標記為「工程實作欄位」，待全組確認前不得視為正式需求：
 *   - category（項目分類，暫定，供未來篩選/分組用）
 *   - default_cycle_days（預設保養週期天數，建立保養計畫時可被覆寫）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->comment('保養項目名稱');
            $table->string('category', 100)->nullable()->comment('工程實作欄位：項目分類，暫定');
            $table->text('description')->nullable()->comment('項目說明');
            $table->unsignedInteger('default_cycle_days')->nullable()
                ->comment('工程實作欄位：預設保養週期（天），建立計畫時可覆寫');
            $table->boolean('is_active')->default(true)->comment('啟用/停用，取代刪除');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_items');
    }
};
