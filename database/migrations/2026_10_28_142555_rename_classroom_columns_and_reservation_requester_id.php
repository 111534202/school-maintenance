<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 將教室與預約資料表欄位統一為資料庫命名規則。
     * 使用欄位改名保留既有資料。
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['room_code']);
            $table->renameColumn('room_code', 'classroom_code');
            $table->renameColumn('room_name', 'name');
            $table->renameColumn('room_type', 'type');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unique('classroom_code', 'classrooms_classroom_code_unique');
        });

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'reservation_date']);
            $table->renameColumn('user_id', 'requester_id');
        });

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->foreign('requester_id', 'classroom_reservations_requester_id_foreign')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index(
                ['requester_id', 'reservation_date'],
                'classroom_reservations_requester_id_reservation_date_index'
            );
        });
    }

    /**
     * 回復原欄位名稱，保留資料。
     */
    public function down(): void
    {
        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->dropForeign(['requester_id']);
            $table->dropIndex(['requester_id', 'reservation_date']);
            $table->renameColumn('requester_id', 'user_id');
        });

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->foreign('user_id', 'classroom_reservations_user_id_foreign')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index(
                ['user_id', 'reservation_date'],
                'classroom_reservations_user_id_reservation_date_index'
            );
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['classroom_code']);
            $table->renameColumn('classroom_code', 'room_code');
            $table->renameColumn('name', 'room_name');
            $table->renameColumn('type', 'room_type');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unique('room_code', 'classrooms_room_code_unique');
        });
    }
};
