<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_reservation_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('classroom_reservation_id')
                ->constrained('classroom_reservations')
                ->cascadeOnDelete();

            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('period_no')->nullable();

            $table->timestamps();

            $table->index(
                ['classroom_reservation_id', 'reservation_date'],
                'reservation_slots_reservation_date_index'
            );

            $table->index(
                ['reservation_date', 'start_time', 'end_time'],
                'reservation_slots_time_range_index'
            );
        });

        DB::table('classroom_reservations')
            ->select([
                'id',
                'reservation_date',
                'start_time',
                'end_time',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunk(500, function ($reservations): void {
                $now = now();
                $slots = [];

                foreach ($reservations as $reservation) {
                    $slots[] = [
                        'classroom_reservation_id' => $reservation->id,
                        'reservation_date' => $reservation->reservation_date,
                        'start_time' => $reservation->start_time,
                        'end_time' => $reservation->end_time,
                        'period_no' => null,
                        'created_at' => $reservation->created_at ?? $now,
                        'updated_at' => $reservation->updated_at ?? $now,
                    ];
                }

                if ($slots !== []) {
                    DB::table('classroom_reservation_slots')->insert($slots);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_reservation_slots');
    }
};