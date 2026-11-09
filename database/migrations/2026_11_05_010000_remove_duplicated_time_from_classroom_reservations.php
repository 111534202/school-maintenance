<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 教室預約的使用時間只記一份：拿掉申請本身（classroom_reservations）的 reservation_date、start_time、end_time，
// 一律以時段明細（classroom_reservation_slots）為準。原因與討論見 docs/待確認/預約時間重複儲存_劉家芸.md（方案 A）。
// 1. 先把「沒有任何時段」的舊申請，用它自己的時間補成一個時段（審核結果跟著整筆申請，所以時段的 status 留空）。
// 2. 再移除三個欄位與用到它們的索引。
// down() 會把欄位加回來，並用每筆申請最早的時段填回去。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classroom_reservations', 'reservation_date')) {
            return;
        }

        $now = now();
        DB::table('classroom_reservations')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('classroom_reservation_slots')
                ->whereColumn('classroom_reservation_slots.classroom_reservation_id', 'classroom_reservations.id'))
            ->orderBy('id')
            ->get(['id', 'reservation_date', 'start_time', 'end_time'])
            ->each(fn ($reservation) => DB::table('classroom_reservation_slots')->insert([
                'classroom_reservation_id' => $reservation->id,
                'reservation_date' => $reservation->reservation_date,
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
                'period_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        // 外鍵（classroom_id、requester_id）在 MySQL 需要一個以它開頭的索引；原本用的是含 reservation_date 的複合索引，
        // 所以先各補一個單欄索引，再拿掉含 reservation_date 的索引（索引名稱依當初建立時而定，例如還是 user_id 舊名，所以用查的）。
        foreach (['classroom_id', 'requester_id'] as $column) {
            if (! Schema::hasIndex('classroom_reservations', [$column])) {
                Schema::table('classroom_reservations', fn (Blueprint $table) => $table->index($column));
            }
        }
        foreach (Schema::getIndexes('classroom_reservations') as $index) {
            if (in_array('reservation_date', $index['columns'], true)) {
                Schema::table('classroom_reservations', fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
        }

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->dropColumn(['reservation_date', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('classroom_reservations', 'reservation_date')) {
            return;
        }

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->date('reservation_date')->nullable()->after('class_name');
            $table->time('start_time')->nullable()->after('reservation_date');
            $table->time('end_time')->nullable()->after('start_time');
        });

        DB::table('classroom_reservations')->orderBy('id')->get(['id'])->each(function ($reservation) {
            $first = DB::table('classroom_reservation_slots')
                ->where('classroom_reservation_id', $reservation->id)
                ->orderBy('reservation_date')->orderBy('start_time')
                ->first(['reservation_date', 'start_time', 'end_time']);
            if ($first) {
                DB::table('classroom_reservations')->where('id', $reservation->id)->update((array) $first);
            }
        });

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->index(['classroom_id', 'reservation_date']);
            $table->index(['requester_id', 'reservation_date']);
        });
    }
};
