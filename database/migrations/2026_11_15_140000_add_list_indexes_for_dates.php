<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 常用的「依日期排序／篩選」欄位補索引：資料變多（操作紀錄、報修單、保養工單）之後，列表與主控台仍然快。
 * 目前資料量小，看不出差別；這是預防性的優化，不改任何功能或欄位。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('repair_requests', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('maintenance_orders', fn (Blueprint $table) => $table->index('scheduled_date'));
        Schema::table('maintenance_plans', fn (Blueprint $table) => $table->index('next_due_date'));
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('repair_requests', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('maintenance_orders', fn (Blueprint $table) => $table->dropIndex(['scheduled_date']));
        Schema::table('maintenance_plans', fn (Blueprint $table) => $table->dropIndex(['next_due_date']));
    }
};
