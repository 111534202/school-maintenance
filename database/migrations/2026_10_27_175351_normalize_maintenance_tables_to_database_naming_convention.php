<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * 以精確名稱取得或建立設備分類。
     */
    private function resolveCategoryId(string $name): int
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('設備分類名稱不可為空。');
        }

        $exactCategory = DB::table('device_categories')
            ->whereRaw('BINARY name = BINARY ?', [$name])
            ->first(['id']);

        if ($exactCategory !== null) {
            return (int) $exactCategory->id;
        }

        // 資料庫使用不區分大小寫的 collation。
        // 若一般比對找到不同文字，必須停止，不能默默合併分類。
        $collationMatch = DB::table('device_categories')
            ->where('name', $name)
            ->first(['id', 'name']);

        if ($collationMatch !== null) {
            throw new RuntimeException(
                "設備分類名稱 {$name} 與既有分類 {$collationMatch->name} "
                . '只有大小寫或 collation 差異，無法安全合併。'
            );
        }

        DB::table('device_categories')->insert([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = DB::table('device_categories')
            ->whereRaw('BINARY name = BINARY ?', [$name])
            ->first(['id']);

        if ($category === null) {
            throw new RuntimeException(
                "無法建立設備分類 {$name}。"
            );
        }

        return (int) $category->id;
    }
    /**
     * 檢查舊分類文字與指定設備的分類是否一致。
     */
    private function validateDeviceCategory(
        string $table,
        int $rowId,
        int $deviceId,
        ?string $oldCategory
    ): void {
        if ($oldCategory === null || trim($oldCategory) === '') {
            return;
        }

        $deviceCategory = DB::table('devices')
            ->leftJoin(
                'device_categories',
                'devices.device_category_id',
                '=',
                'device_categories.id'
            )
            ->where('devices.id', $deviceId)
            ->value('device_categories.name');

        if ($deviceCategory === null || trim($oldCategory) !== $deviceCategory) {
            throw new RuntimeException(
                "{$table} ID {$rowId} 的舊分類「{$oldCategory}」"
                . "與設備 ID {$deviceId} 的分類不一致，Migration 已停止。"
            );
        }
    }

    /**
     * 僅在外鍵不存在時建立。
     * 若同一欄位已有不同參照目標，則停止 Migration。
     */
    private function addForeignKeyIfMissing(
        string $table,
        string $column,
        string $referencedTable,
        string $foreignKeyName
    ): void {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) !== [$column]) {
                continue;
            }

            $sameTarget = ($foreignKey['foreign_table'] ?? null) === $referencedTable
                && ($foreignKey['foreign_columns'] ?? []) === ['id'];

            if (! $sameTarget) {
                throw new RuntimeException(
                    "{$table}.{$column} 已有不同參照目標的外鍵，無法安全建立。"
                );
            }

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use (
            $column,
            $referencedTable,
            $foreignKeyName
        ) {
            $blueprint->foreign($column, $foreignKeyName)
                ->references('id')
                ->on($referencedTable)
                ->nullOnDelete();
        });
    }

    /**
     * 只刪除指定名稱的外鍵，避免誤刪原有外鍵。
     */
    private function dropMigrationForeignKeyIfExists(
        string $table,
        string $foreignKeyName,
        string $column,
        string $referencedTable
    ): void {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['name'] ?? null) !== $foreignKeyName) {
                continue;
            }

            $sameTarget = ($foreignKey['columns'] ?? []) === [$column]
                && ($foreignKey['foreign_table'] ?? null) === $referencedTable
                && ($foreignKey['foreign_columns'] ?? []) === ['id'];

            if (! $sameTarget) {
                throw new RuntimeException(
                    "外鍵 {$foreignKeyName} 的定義與預期不同，停止回復。"
                );
            }

            Schema::table($table, function (Blueprint $blueprint) use ($foreignKeyName) {
                $blueprint->dropForeign($foreignKeyName);
            });

            return;
        }
    }
    public function up(): void
    {
        /*
         * 1. 前置檢查：必要資料表及欄位。
         */
        $requiredColumns = [
            'maintenance_items' => ['id', 'category'],
            'maintenance_plans' => ['id', 'device_id', 'device_category'],
            'maintenance_orders' => ['id', 'device_id', 'device_category'],
            'maintenance_results' => [
                'id',
                'executed_by',
                'notes',
                'repair_request_id',
            ],
            'devices' => ['id', 'device_category_id'],
            'device_categories' => ['id', 'name'],
            'users' => ['id', 'name'],
            'knowledge_base' => ['id', 'created_by'],
            'repair_requests' => ['id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("必要資料表 {$table} 不存在。");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "必要欄位 {$table}.{$column} 不存在。"
                    );
                }
            }
        }

        foreach ([
            'maintenance_items' => 'category',
            'maintenance_plans' => 'device_category',
            'maintenance_orders' => 'device_category',
            'maintenance_results' => 'executed_by',
        ] as $table => $column) {
            $newColumn = match ($table) {
                'maintenance_items' => 'device_category_id',
                'maintenance_plans', 'maintenance_orders' => 'device_category_id',
                'maintenance_results' => 'legacy_executed_by',
            };

            if (Schema::hasColumn($table, $newColumn)) {
                throw new RuntimeException(
                    "{$table}.{$newColumn} 已存在，請先確認資料庫狀態。"
                );
            }
        }

        if (Schema::hasColumn('maintenance_results', 'note')) {
            throw new RuntimeException(
                'maintenance_results.note 已存在，不能直接改名。'
            );
        }

        /*
         * 2. 檢查既有外鍵資料是否有孤兒紀錄。
         */
        $orphanChecks = [
            ['maintenance_plans', 'device_id', 'devices'],
            ['maintenance_orders', 'device_id', 'devices'],
            ['maintenance_results', 'repair_request_id', 'repair_requests'],
            ['knowledge_base', 'created_by', 'users'],
        ];

        foreach ($orphanChecks as [$table, $column, $parentTable]) {
            $hasOrphan = DB::table($table)
                ->whereNotNull("{$table}.{$column}")
                ->whereNotIn(
                    "{$table}.{$column}",
                    DB::table($parentTable)->select('id')
                )
                ->exists();

            if ($hasOrphan) {
                throw new RuntimeException(
                    "{$table}.{$column} 存在找不到對應 {$parentTable}.id 的資料。"
                );
            }
        }

        /*
         * 3. 確認使用者名稱沒有重複，避免執行人員無法唯一對應。
         */
        $legacyExecutorNames = DB::table('maintenance_results')
            ->whereNotNull('executed_by')
            ->distinct()
            ->pluck('executed_by');

        foreach ($legacyExecutorNames as $legacyName) {
            $name = trim($legacyName);

            if ($name === '') {
                continue;
            }

            $matchingUsers = DB::table('users')
                ->whereRaw('BINARY name = BINARY ?', [$name])
                ->count();

            if ($matchingUsers > 1) {
                throw new RuntimeException(
                    "執行人 {$name} 對應到多筆使用者資料（包含軟刪除帳號），"
                    . '無法安全轉換 maintenance_results.executed_by。'
                );
            }
        }


        /*
         * 4. 預先檢查執行人員標記是否會超過 TEXT 欄位容量。
         *
         * MySQL TEXT 最大容量為 65,535 位元組。
         * 必須在任何資料表結構變更之前完成檢查。
         */
        DB::table('maintenance_results')
            ->whereNotNull('executed_by')
            ->orderBy('id')
            ->get(['id', 'executed_by', 'notes'])
            ->each(function ($row) {
                $originalName = (string) $row->executed_by;

                $marker = '[[legacy_executed_by:'
                    . base64_encode($originalName)
                    . ']]';

                $note = (string) ($row->notes ?? '');

                $separator = $note === '' ? '' : "\n";

                $requiredBytes = strlen($note)
                    + strlen($separator)
                    + strlen($marker);

                if ($requiredBytes > 65535) {
                    throw new RuntimeException(
                        "maintenance_results ID {$row->id} 的備註加上執行人員標記後，"
                        . "將超過 TEXT 欄位容量（{$requiredBytes} 位元組）。"
                        . '請先處理該筆資料，再執行 Migration。'
                    );
                }
            });

        /*
         * 5. 先檢查設備綁定分類的舊資料是否一致。
         */
        foreach (['maintenance_plans', 'maintenance_orders'] as $table) {
            DB::table($table)
                ->whereNotNull('device_id')
                ->whereNotNull('device_category')
                ->orderBy('id')
                ->get(['id', 'device_id', 'device_category'])
                ->each(function ($row) use ($table) {
                    $this->validateDeviceCategory(
                        $table,
                        (int) $row->id,
                        (int) $row->device_id,
                        $row->device_category
                    );
                });
        }

        /*
         * 6. 新增分類外鍵欄位，先不刪除舊文字欄位。
         */
        foreach ([
            'maintenance_items',
            'maintenance_plans',
            'maintenance_orders',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('device_category_id')->nullable();
            });
        }

        /*
         * 7. 轉換 maintenance_items 分類。
         */
        DB::table('maintenance_items')
            ->whereNotNull('category')
            ->orderBy('id')
            ->get(['id', 'category'])
            ->each(function ($row) {
                $category = trim($row->category);

                if ($category === '') {
                    return;
                }

                $categoryId = $this->resolveCategoryId($category);

                DB::table('maintenance_items')
                    ->where('id', $row->id)
                    ->update(['device_category_id' => $categoryId]);
            });

        /*
         * 8. 轉換 maintenance_plans / maintenance_orders 分類。
         *
         * 只要舊分類文字有值，就轉換成 device_category_id。
         * 有指定設備時，前置檢查已確認舊分類與設備分類一致。
         * 保留原分類 ID，讓 down() 能依原始資料精確還原。
         */
        foreach (['maintenance_plans', 'maintenance_orders'] as $table) {
            DB::table($table)
                ->whereNotNull('device_category')
                ->orderBy('id')
                ->get(['id', 'device_category'])
                ->each(function ($row) use ($table) {
                    $category = trim($row->device_category);

                    if ($category === '') {
                        return;
                    }

                    $categoryId = $this->resolveCategoryId($category);

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['device_category_id' => $categoryId]);
                });
        }

        /*
         * 9. 維修結果：保留舊執行人員名稱。
         *
         * 所有舊執行人員名稱都會以 Base64 標記附加到 note。
         * 即使成功對應使用者，也保留原始名稱供 down() 還原。
         */
        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->renameColumn('notes', 'note');
            $table->renameColumn('executed_by', 'legacy_executed_by');
        });

        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->unsignedBigInteger('executed_by')->nullable();
        });

        DB::table('maintenance_results')
            ->whereNotNull('legacy_executed_by')
            ->orderBy('id')
            ->get(['id', 'legacy_executed_by', 'note'])
            ->each(function ($row) {
                $originalName = (string) $row->legacy_executed_by;
                $name = trim($originalName);

                $user = $name === ''
                    ? null
                    : DB::table('users')
                        ->whereRaw('BINARY name = BINARY ?', [$name])
                        ->first(['id']);

                $marker = '[[legacy_executed_by:'
                    . base64_encode($originalName)
                    . ']]';

                $note = $row->note === null || $row->note === ''
                    ? $marker
                    : $row->note . "\n" . $marker;

                DB::table('maintenance_results')
                    ->where('id', $row->id)
                    ->update([
                        'executed_by' => $user === null ? null : $user->id,
                        'note' => $note,
                    ]);
            });

        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->dropColumn('legacy_executed_by');
        });

        /*
         * 10. 移除舊分類文字欄位。
         */
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        foreach (['maintenance_plans', 'maintenance_orders'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('device_category');
            });
        }

        /*
         * 建立本 Migration 需要的外鍵。
         * 使用專屬名稱，讓 down() 能精確辨認並移除。
         */
        $foreignKeys = [
            ['maintenance_items', 'device_category_id', 'device_categories', 'maint_items_category_mig_fk'],
            ['maintenance_plans', 'device_category_id', 'device_categories', 'maint_plans_category_mig_fk'],
            ['maintenance_orders', 'device_category_id', 'device_categories', 'maint_orders_category_mig_fk'],
            ['maintenance_plans', 'device_id', 'devices', 'maint_plans_device_mig_fk'],
            ['maintenance_orders', 'device_id', 'devices', 'maint_orders_device_mig_fk'],
            ['maintenance_results', 'executed_by', 'users', 'maint_results_executor_mig_fk'],
            ['maintenance_results', 'repair_request_id', 'repair_requests', 'maint_results_repair_mig_fk'],
            ['knowledge_base', 'created_by', 'users', 'knowledge_base_creator_mig_fk'],
        ];

        foreach ($foreignKeys as [$table, $column, $parentTable, $name]) {
            $this->addForeignKeyIfMissing(
                $table,
                $column,
                $parentTable,
                $name
            );
        }
    }

    public function down(): void
    {
        /*
         * 回滾前先確認必要欄位仍存在。
         */
        foreach ([
            'maintenance_items' => ['device_category_id'],
            'maintenance_plans' => ['device_category_id', 'device_id'],
            'maintenance_orders' => ['device_category_id', 'device_id'],
            'maintenance_results' => ['executed_by', 'note'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("回滾時找不到資料表 {$table}。");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "回滾時找不到欄位 {$table}.{$column}。"
                    );
                }
            }
        }

        /*
         * 回滾前確認舊欄位尚未存在，避免重複建立。
         */
        foreach ([
            'maintenance_items' => ['category'],
            'maintenance_plans' => ['device_category'],
            'maintenance_orders' => ['device_category'],
            'maintenance_results' => ['legacy_executed_by'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "回滾前欄位 {$table}.{$column} 已存在，停止回復。"
                    );
                }
            }
        }

        /*
         * 確認回復所需的參照資料表及欄位存在。
         */
        foreach ([
            'device_categories' => ['id', 'name'],
            'users' => ['id', 'name'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "回滾時找不到必要資料表 {$table}。"
                );
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException(
                        "回滾時找不到必要欄位 {$table}.{$column}。"
                    );
                }
            }
        }

        /*
         * 確認每筆分類 ID 都能找到原始分類，避免回復時漏掉分類文字。
         */
        foreach ([
            'maintenance_items',
            'maintenance_plans',
            'maintenance_orders',
        ] as $table) {
            $hasMissingCategory = DB::table($table . ' as source')
                ->leftJoin(
                    'device_categories as categories',
                    'categories.id',
                    '=',
                    'source.device_category_id'
                )
                ->whereNotNull('source.device_category_id')
                ->whereNull('categories.id')
                ->exists();

            if ($hasMissingCategory) {
                throw new RuntimeException(
                    "{$table} 存在找不到對應分類的資料，停止回復以避免分類遺失。"
                );
            }
        }

        /*
         * 分類名稱必須符合舊欄位 varchar(100) 的長度限制。
         */
        foreach ([
            'maintenance_items',
            'maintenance_plans',
            'maintenance_orders',
        ] as $table) {
            $hasOverlongCategory = DB::table($table . ' as source')
                ->join(
                    'device_categories as categories',
                    'categories.id',
                    '=',
                    'source.device_category_id'
                )
                ->whereNotNull('source.device_category_id')
                ->whereRaw('CHAR_LENGTH(categories.name) > 100')
                ->exists();

            if ($hasOverlongCategory) {
                throw new RuntimeException(
                    "{$table} 存在超過 100 字元的分類名稱，停止回復以避免資料截斷。"
                );
            }
        }

        /*
         * 執行人員名稱及原始名稱標記必須符合舊欄位 varchar(100)。
         * 同時預先檢查標記能否正確解碼。
         */
        DB::table('maintenance_results')
            ->orderBy('id')
            ->get(['id', 'executed_by', 'note'])
            ->each(function ($row) {
                $name = null;
                $note = (string) ($row->note ?? '');

                if ($row->executed_by !== null) {
                    $name = DB::table('users')
                        ->where('id', $row->executed_by)
                        ->value('name');
                }

                if (preg_match(
                    '/(?:^|\n)\[\[legacy_executed_by:([A-Za-z0-9+\/=]*)\]\]$/',
                    $note,
                    $matches
                )) {
                    $decoded = base64_decode($matches[1], true);

                    if ($decoded === false) {
                        throw new RuntimeException(
                            "維修結果 ID {$row->id} 的執行人員標記無法解碼，停止回復。"
                        );
                    }

                    $name = $decoded;
                }

                if ($name !== null && mb_strlen($name, 'UTF-8') > 100) {
                    throw new RuntimeException(
                        "維修結果 ID {$row->id} 的執行人員名稱超過 100 字元，停止回復。"
                    );
                }
            });

        /*
         * 1. 先還原舊文字欄位。
         */
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->string('category', 100)->nullable();
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->string('device_category', 100)->nullable();
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('device_category', 100)->nullable();
        });

        /*
         * 2. 從分類 ID 還原分類文字。
         */
        foreach ([
            'maintenance_items',
            'maintenance_plans',
            'maintenance_orders',
        ] as $table) {
            DB::table($table)
                ->whereNotNull('device_category_id')
                ->orderBy('id')
                ->get(['id', 'device_category_id'])
                ->each(function ($row) use ($table) {
                    $name = DB::table('device_categories')
                        ->where('id', $row->device_category_id)
                        ->value('name');

                    if ($name !== null) {
                        $oldColumn = $table === 'maintenance_items'
                            ? 'category'
                            : 'device_category';

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$oldColumn => $name]);
                    }
                });
        }


        /*
         * 3. 還原執行人員文字欄位。
         */
        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->string('legacy_executed_by', 100)->nullable();
        });

        DB::table('maintenance_results')
            ->orderBy('id')
            ->get(['id', 'executed_by', 'note'])
            ->each(function ($row) {
                $name = null;
                $note = $row->note;

                if ($row->executed_by !== null) {
                    $name = DB::table('users')
                        ->where('id', $row->executed_by)
                        ->value('name');
                }

                if (preg_match(
                    '/(?:^|\n)\[\[legacy_executed_by:([A-Za-z0-9+\/=]*)\]\]$/',
                    (string) $note,
                    $matches
                )) {
                    $decoded = base64_decode($matches[1], true);

                    if ($decoded === false) {
                        throw new RuntimeException(
                            "維修結果 ID {$row->id} 的執行人員標記無法解碼，停止回復。"
                        );
                    }

                    $name = $decoded;

                    $note = preg_replace(
                        '/(?:^|\n)\[\[legacy_executed_by:[A-Za-z0-9+\/=]*\]\]$/',
                        '',
                        (string) $note
                    );

                    $note = $note === '' ? null : $note;
                }

                DB::table('maintenance_results')
                    ->where('id', $row->id)
                    ->update([
                        'legacy_executed_by' => $name,
                        'note' => $note,
                    ]);
            });

        /*
         * 4. 移除本 Migration 新增的外鍵。
         */
        // 只移除本變更檔建立的外鍵，保留原本已存在的外鍵。
        $foreignKeys = [
            ['maintenance_items', 'device_category_id', 'device_categories', 'maint_items_category_mig_fk'],
            ['maintenance_plans', 'device_category_id', 'device_categories', 'maint_plans_category_mig_fk'],
            ['maintenance_orders', 'device_category_id', 'device_categories', 'maint_orders_category_mig_fk'],
            ['maintenance_plans', 'device_id', 'devices', 'maint_plans_device_mig_fk'],
            ['maintenance_orders', 'device_id', 'devices', 'maint_orders_device_mig_fk'],
            ['maintenance_results', 'executed_by', 'users', 'maint_results_executor_mig_fk'],
            ['maintenance_results', 'repair_request_id', 'repair_requests', 'maint_results_repair_mig_fk'],
            ['knowledge_base', 'created_by', 'users', 'knowledge_base_creator_mig_fk'],
        ];

        foreach ($foreignKeys as [$table, $column, $parentTable, $name]) {
            $this->dropMigrationForeignKeyIfExists(
                $table,
                $name,
                $column,
                $parentTable
            );
        }

        /*
         * 5. 移除新欄位，保留舊文字欄位。
         */
        foreach ([
            'maintenance_items',
            'maintenance_plans',
            'maintenance_orders',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('device_category_id');
            });
        }

        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->dropColumn('executed_by');
            $table->renameColumn('legacy_executed_by', 'executed_by');
            $table->renameColumn('note', 'notes');
        });
    }
};