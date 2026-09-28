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
        Schema::table('device_categories', function (Blueprint $table) {
            $table->unique('name'); // Controller 已檢查唯一性，補上 DB 層級保證
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
