<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 將報修人欄位 reporter_id 改為 requester_id。
     */
    public function up(): void
    {
        $hasOldColumn = Schema::hasColumn('repair_requests', 'reporter_id');
        $hasNewColumn = Schema::hasColumn('repair_requests', 'requester_id');

        if ($hasOldColumn && $hasNewColumn) {
            throw new RuntimeException(
                'repair_requests 同時存在 reporter_id 與 requester_id，請先確認資料庫欄位狀態。'
            );
        }

        if (! $hasOldColumn && ! $hasNewColumn) {
            throw new RuntimeException(
                'repair_requests 找不到 reporter_id 或 requester_id，無法安全執行欄位改名。'
            );
        }

        if ($hasOldColumn) {
            $this->dropForeignKeyForColumn('reporter_id');

            Schema::table('repair_requests', function (Blueprint $table) {
                $table->renameColumn('reporter_id', 'requester_id');
            });
        }

        $this->ensureUserForeignKey('requester_id');
    }

    /**
     * 將 requester_id 回復為 reporter_id。
     */
    public function down(): void
    {
        $hasOldColumn = Schema::hasColumn('repair_requests', 'reporter_id');
        $hasNewColumn = Schema::hasColumn('repair_requests', 'requester_id');

        if ($hasOldColumn && $hasNewColumn) {
            throw new RuntimeException(
                'repair_requests 同時存在 reporter_id 與 requester_id，無法安全回復。'
            );
        }

        if (! $hasOldColumn && ! $hasNewColumn) {
            throw new RuntimeException(
                'repair_requests 找不到 reporter_id 或 requester_id，無法安全回復欄位名稱。'
            );
        }

        if ($hasNewColumn) {
            $this->dropForeignKeyForColumn('requester_id');

            Schema::table('repair_requests', function (Blueprint $table) {
                $table->renameColumn('requester_id', 'reporter_id');
            });
        }

        $this->ensureUserForeignKey('reporter_id');
    }

    /**
     * 取得指定欄位指向 users 資料表的外鍵名稱，並在存在時移除。
     */
    private function dropForeignKeyForColumn(string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys('repair_requests'))
            ->first(function (array $foreignKey) use ($column): bool {
                return in_array($column, $foreignKey['columns'], true)
                    && $foreignKey['foreign_table'] === 'users';
            });

        if ($foreignKey === null) {
            return;
        }

        Schema::table('repair_requests', function (Blueprint $table) use ($foreignKey, $column): void {
            if ($foreignKey['name'] === null) {
                $table->dropForeign([$column]);
            } else {
                $table->dropForeign($foreignKey['name']);
            }
        });
    }

    /**
     * 確保指定欄位具有指向 users.id 的外鍵。
     */
    private function ensureUserForeignKey(string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys('repair_requests'))
            ->first(function (array $foreignKey) use ($column): bool {
                return in_array($column, $foreignKey['columns'], true)
                    && $foreignKey['foreign_table'] === 'users';
            });

        if ($foreignKey === null) {
            Schema::table('repair_requests', function (Blueprint $table) use ($column): void {
                $table->foreign($column)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }
};