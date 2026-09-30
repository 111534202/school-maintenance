<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 桌上型電腦、螢幕、筆電、投影機、交換器、無線AP、印表機...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_categories');
    }
};
