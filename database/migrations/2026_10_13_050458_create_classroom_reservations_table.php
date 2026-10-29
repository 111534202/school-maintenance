<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('classroom_id')
                ->constrained('classrooms')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('class_name', 100)
                ->comment('申請人填寫的班級名稱');

            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->string('status', 20)->default('pending');
            $table->string('purpose', 255)->nullable();

            $table->timestamps();

            $table->index(['classroom_id', 'reservation_date']);
            $table->index(['user_id', 'reservation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_reservations');
    }
};