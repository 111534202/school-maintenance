<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_code')->unique(); // 設備編號
            $table->string('asset_code')->nullable(); // 資產編號
            $table->foreignId('device_category_id')->constrained('device_categories');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('warranty_until')->nullable(); // 保固期限
            $table->foreignId('classroom_id')->constrained('classrooms');
            $table->string('status')->default('normal'); // 設備狀態：normal, repairing, retired...
            // 是否為核心設備：影響空間預約狀態標示，跟劉家芸的模組串接點，異動要先告知她
            $table->boolean('is_core')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
