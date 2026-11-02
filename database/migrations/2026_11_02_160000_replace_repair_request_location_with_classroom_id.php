<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 報修單的「設備地點」從自由輸入的文字（location）改成只能選現有教室（classroom_id → classrooms）。
 *
 * 舊資料轉換：
 * - location 的文字對得上教室代碼或教室名稱（不分大小寫）→ 填入那間教室。
 * - location 沒填，但報修單有綁設備 → 用設備當時所在的教室。
 * - 對不上任何教室的文字不丟掉，補寫進報修說明的最後（「原填寫地點：…」）。
 *
 * classroom_id 記的是「報修當時」的地點：設備之後搬到別的教室，舊報修單的地點不會跟著變。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('device_note')
                ->constrained('classrooms')->nullOnDelete();
        });

        $classrooms = DB::table('classrooms')->get(['id', 'classroom_code', 'name']);
        $findClassroom = function (string $text) use ($classrooms) {
            $needle = mb_strtolower(trim($text));

            return $classrooms->first(fn ($c) => mb_strtolower((string) $c->classroom_code) === $needle || mb_strtolower((string) $c->name) === $needle)?->id;
        };

        foreach (DB::table('repair_requests')->get(['id', 'location', 'device_id', 'description']) as $row) {
            $location = trim((string) $row->location);
            $classroomId = null;
            $description = $row->description;

            if ($location !== '') {
                $classroomId = $findClassroom($location);
                if ($classroomId === null) {
                    $description = rtrim((string) $description) . "\n（原填寫地點：{$location}）";
                }
            } elseif ($row->device_id) {
                $classroomId = DB::table('devices')->where('id', $row->device_id)->value('classroom_id');
            }

            DB::table('repair_requests')->where('id', $row->id)->update([
                'classroom_id' => $classroomId,
                'description' => $description,
            ]);
        }

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }

    public function down(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->string('location')->nullable()->after('device_note');
        });

        foreach (DB::table('repair_requests')->whereNotNull('classroom_id')->get(['id', 'classroom_id']) as $row) {
            DB::table('repair_requests')->where('id', $row->id)->update([
                'location' => DB::table('classrooms')->where('id', $row->classroom_id)->value('classroom_code'),
            ]);
        }

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
