<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 將報修單是否影響上課欄位改為 is_class_affected。
     * 使用欄位改名保留既有資料。
     */
    public function up(): void
    {
        $hasOldColumn = Schema::hasColumn('repair_requests', 'affects_class');
        $hasNewColumn = Schema::hasColumn('repair_requests', 'is_class_affected');

        if ($hasOldColumn && ! $hasNewColumn) {
            Schema::table('repair_requests', function ($table) {
                $table->renameColumn('affects_class', 'is_class_affected');
            });

            return;
        }

        if (! $hasOldColumn && $hasNewColumn) {
            return;
        }

        throw new RuntimeException(
            'repair_requests 欄位狀態異常：affects_class 與 is_class_affected 必須且只能存在其中一個。'
        );
    }

    /**
     * 回復原欄位名稱，保留既有資料。
     */
    public function down(): void
    {
        $hasOldColumn = Schema::hasColumn('repair_requests', 'affects_class');
        $hasNewColumn = Schema::hasColumn('repair_requests', 'is_class_affected');

        if ($hasNewColumn && ! $hasOldColumn) {
            Schema::table('repair_requests', function ($table) {
                $table->renameColumn('is_class_affected', 'affects_class');
            });

            return;
        }

        if ($hasOldColumn && ! $hasNewColumn) {
            return;
        }

        throw new RuntimeException(
            'repair_requests 欄位狀態異常：affects_class 與 is_class_affected 必須且只能存在其中一個。'
        );
    }
};