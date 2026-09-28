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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // 操作人，帳號被停用/軟刪除也不影響歷史記錄
            $table->string('action'); // 例如：login, created, updated, status_changed
            $table->string('loggable_type')->nullable(); // 多型關聯：被操作的模型類別
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->json('changes')->nullable(); // 變更內容，例如 {"from":"normal","to":"repairing"}
            $table->string('description')->nullable(); // 人類可讀描述
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
