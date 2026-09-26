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
        // 依《第三週進度安排》第 1 項升級為「正式」版本：新增故障描述/影響程度/是否影響上課等
        // 規格已明確欄位。devices/users 表仍不在這個暫存專案裡（尚未有共用 Repository 可合併），
        // 所以 reporter_id / device_id / assigned_to 依然不加外鍵約束，避免建立假的替代表；
        // 設備先用 device_note 文字欄位頂著，等 devices 表真的合併後再換成 device_id 下拉選單。
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->comment('故障描述');
            // 待確認：正式列舉值僅為草案，之後跟林政寬/王佑恩對齊全系統的狀態命名慣例
            $table->enum('impact_level', ['low', 'medium', 'high'])->default('medium')->comment('影響程度');
            $table->boolean('affects_class')->default(false)->comment('是否影響上課');
            $table->string('status')->default('pending')->comment('案件狀態：pending/assigned/in_progress/completed/closed（草案）');
            // 待補 FK -> users.id（報修人），users 表由林政寬建立中，尚未合併進本專案
            $table->unsignedBigInteger('reporter_id')->nullable()->comment('待補 FK -> users.id');
            // 待補 FK -> devices.id，devices 表由林政寬建立中，尚未合併進本專案
            $table->unsignedBigInteger('device_id')->nullable()->comment('待補 FK -> devices.id，devices 表尚未合併');
            // devices 表正式合併前的暫時做法：直接讓使用者用文字描述/指認設備
            $table->string('device_note')->nullable()->comment('devices 表合併前，暫時用文字描述設備；合併後改用 device_id 下拉');
            // 待補 FK -> users.id（維修人員）
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('待補 FK -> users.id（維修人員）');
            $table->string('location')->nullable()->comment('待確認：是否改關聯 classrooms，目前先用文字紀錄地點');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_requests');
    }
};
