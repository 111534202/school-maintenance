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
        // 表名依規格文件固定為 knowledge_base（單數），不是 Laravel 預設的複數命名。
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable()->comment('待確認：是否改關聯 device_categories，目前該表未建立，先用文字欄位');
            $table->text('symptom')->comment('常見故障現象/問題描述');
            $table->text('solution')->comment('自助排除步驟');
            $table->boolean('is_published')->default(true);
            // 待確認：users 表由林政寬本週建立中，尚未確定可否安全關聯，先用不加外鍵的欄位記錄建立者
            $table->unsignedBigInteger('created_by')->nullable()->comment('待補 FK -> users.id，users 表穩定後再加');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
