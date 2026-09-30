<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('campus');          // 校區
            $table->string('building');        // 大樓
            $table->string('floor');           // 樓層
            $table->string('room_code')->unique(); // 教室代碼
            $table->string('room_name');       // 教室名稱
            $table->string('room_type')->nullable(); // 教室類型
            $table->foreignId('manager_id')->nullable()
                  ->constrained('users')->nullOnDelete(); // 管理人
            $table->boolean('is_active')->default(true); // 啟用狀態
            // 空間借用狀態：normal=正常開放, abnormal=設備異常暫停外借
            // 這個欄位是跟劉家芸的空間預約模組的串接點，命名跟型別已先定案，異動要先告知她
            $table->enum('reservation_status', ['normal', 'abnormal'])->default('normal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
