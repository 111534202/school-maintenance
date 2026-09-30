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
        Schema::table('classrooms', function (Blueprint $table) {
            $table->softDeletes(); // 與 devices 一致採軟刪除，devices.classroom_id 等歷史外鍵不因刪除失效
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
