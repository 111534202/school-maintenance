<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 自助知識庫：每篇文章的查看次數（view_count）。打開文章一次加 1，列表依查看次數由多到少排序；
 * 修改文章內容不會歸零（不在表單與 fillable 裡）。舊資料從 0 開始。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_base', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
