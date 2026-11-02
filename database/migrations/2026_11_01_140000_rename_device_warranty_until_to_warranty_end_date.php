<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 將設備保固日期欄位改為統一命名。
     * 使用欄位改名保留既有資料。
     */
    public function up(): void
    {
        $hasOldColumn = Schema::hasColumn('devices', 'warranty_until');
        $hasNewColumn = Schema::hasColumn('devices', 'warranty_end_date');

        if ($hasOldColumn && ! $hasNewColumn) {
            Schema::table('devices', function (Blueprint $table) {
                $table->renameColumn('warranty_until', 'warranty_end_date');
            });

            return;
        }

        if (! $hasOldColumn && $hasNewColumn) {
            return;
        }

        throw new RuntimeException(
            '設備保固欄位狀態不符合預期，無法安全改名。'
        );
    }

    /**
     * 回復原欄位名稱，保留資料。
     */
    public function down(): void
    {
        $hasOldColumn = Schema::hasColumn('devices', 'warranty_until');
        $hasNewColumn = Schema::hasColumn('devices', 'warranty_end_date');

        if ($hasNewColumn && ! $hasOldColumn) {
            Schema::table('devices', function (Blueprint $table) {
                $table->renameColumn('warranty_end_date', 'warranty_until');
            });

            return;
        }

        if ($hasOldColumn && ! $hasNewColumn) {
            return;
        }

        throw new RuntimeException(
            '設備保固欄位狀態不符合預期，無法安全回復。'
        );
    }
};