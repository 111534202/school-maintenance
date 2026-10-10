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
        Schema::create('parts', function (Blueprint $table) {
            $table->id(); //編號
            $table->string('name'); //零件名稱
            $table->string('specification')->nullable(); //零件規格
            $table->unsignedInteger('unit_price'); // 單價
            $table->unsignedInteger('current_stock')->default(0); //目前庫存
            $table->unsignedInteger('safety_stock')->default(0); //安全庫存
            $table->timestamps(); //更新時間
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};