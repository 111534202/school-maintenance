<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 零件報廢紀錄：零件還沒用就壞掉、過期或遺失時登記報廢，從庫存扣掉。
// - part_scraps：一次報廢一筆（日期、數量、原因、備註、登記人）。
// - part_scrap_batches：這次報廢扣了哪幾個採購批次（跟用料一樣先進先出；跨兩批時拆成兩列，各記那一批的單價，用來算報廢損失）。
// 取消報廢時，依 part_scrap_batches 把數量加回原本的批次與目前庫存。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_scraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->date('scrap_date');
            $table->unsignedInteger('quantity');
            $table->string('reason', 50);
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['part_id', 'scrap_date']);
        });

        Schema::create('part_scrap_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_scrap_id')->constrained('part_scraps')->cascadeOnDelete();
            $table->foreignId('part_purchase_id')->nullable()->constrained('part_purchases')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_scrap_batches');
        Schema::dropIfExists('part_scraps');
    }
};
