<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 資料庫統一與正規化的守門測試：
 *   1. 欄位命名規則一致（「指向使用者的欄位」「指向其他資料表的欄位」一律有外鍵約束）。
 *   2. 重複存兩份的文字欄位已經移除。
 *   3. 轉換用的 migration（2026_10_15_100000）能把舊格式的資料搬到新欄位，不遺失。
 * 用 DatabaseMigrations（每個測試都重新跑一次 migration），因為第 3 項要退回再重跑最後一支 migration。
 */
class DatabaseNormalizationTest extends TestCase
{
    use DatabaseMigrations;

    // 刻意不加外鍵的欄位：多型關聯的編號（同一欄可能指向不同資料表）與 Laravel 內建的工作階段表。
    private const NO_FOREIGN_KEY_BY_DESIGN = [
        'attachments.attachable_id',
        'audit_logs.loggable_id',
        'sessions.user_id',
    ];

    /** @return list<string> 所有資料表名稱 */
    private function tables(): array
    {
        return array_map(fn ($table) => $table['name'], Schema::getTables());
    }

    /** @return array<string, string> 「資料表.欄位」=> 指向的資料表 */
    private function foreignKeys(): array
    {
        $map = [];
        foreach ($this->tables() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                foreach ($foreignKey['columns'] as $column) {
                    $map["{$table}.{$column}"] = $foreignKey['foreign_table'];
                }
            }
        }

        return $map;
    }

    // 規則一：欄位名稱以 _id 結尾、或是 assigned_to / *_by（指向人）的欄位，一定要有外鍵約束。
    public function test_every_reference_column_has_a_foreign_key(): void
    {
        $foreignKeys = $this->foreignKeys();
        $missing = [];

        foreach ($this->tables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $isReference = str_ends_with($column, '_id') || $column === 'assigned_to' || str_ends_with($column, '_by');
                $key = "{$table}.{$column}";

                if ($isReference && ! isset($foreignKeys[$key]) && ! in_array($key, self::NO_FOREIGN_KEY_BY_DESIGN, true)) {
                    $missing[] = $key;
                }
            }
        }

        $this->assertSame([], $missing, '這些參照欄位沒有外鍵約束：' . implode('、', $missing));
    }

    // 規則二：每張業務資料表的主鍵都叫 id（Laravel 慣例），而且是單一欄位。
    public function test_business_tables_use_a_single_id_primary_key(): void
    {
        $exceptions = ['cache', 'cache_locks', 'password_reset_tokens', 'sessions', 'job_batches', 'migrations'];

        foreach ($this->tables() as $table) {
            if (in_array($table, $exceptions, true)) {
                continue;
            }

            $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);
            $this->assertNotNull($primary, "{$table} 沒有主鍵");
            $this->assertSame(['id'], $primary['columns'], "{$table} 的主鍵應該是單一欄位 id");
        }
    }

    // 規則三：「人」的欄位全部指向 users；設備類別全部指向 device_categories；設備全部指向 devices。
    public function test_reference_columns_point_to_the_expected_tables(): void
    {
        $foreignKeys = $this->foreignKeys();

        $expected = [
            'repair_requests.requester_id' => 'users',
            'repair_requests.assigned_to' => 'users',
            'repair_requests.device_id' => 'devices',
            'classroom_reservations.requester_id' => 'users',
            'classroom_reservations.reviewed_by' => 'users',
            'maintenance_results.executed_by' => 'users',
            'maintenance_results.repair_request_id' => 'repair_requests',
            'maintenance_items.device_category_id' => 'device_categories',
            'maintenance_plans.device_category_id' => 'device_categories',
            'maintenance_plans.device_id' => 'devices',
            'maintenance_orders.device_category_id' => 'device_categories',
            'maintenance_orders.device_id' => 'devices',
            'knowledge_base.created_by' => 'users',
            'repair_part_usages.repair_log_id' => 'repair_logs',
            'repair_part_usages.part_id' => 'parts',
        ];

        foreach ($expected as $column => $table) {
            $this->assertSame($table, $foreignKeys[$column] ?? null, "{$column} 應該指向 {$table}");
        }
    }

    // 規則四：重複存兩份的文字欄位、舊的欄位名稱都已經不存在；資料表名稱統一用複數。
    public function test_duplicated_text_columns_and_old_names_are_gone(): void
    {
        foreach ([
            ['repair_requests', 'assignee_note'],
            ['repair_requests', 'reporter_id'],
            ['classroom_reservations', 'user_id'],
            ['maintenance_items', 'category'],
            ['maintenance_plans', 'device_category'],
            ['maintenance_orders', 'device_category'],
        ] as [$table, $column]) {
            $this->assertFalse(Schema::hasColumn($table, $column), "{$table}.{$column} 應該已經移除");
        }

        $this->assertTrue(Schema::hasTable('repair_part_usages'));
        $this->assertFalse(Schema::hasTable('repair_part_usage'));
        $this->assertTrue(Schema::hasTable('knowledge_base'), '知識庫資料表名稱依規格文件固定為 knowledge_base');
    }

    // 轉換：把舊格式（文字欄位）的資料放進去，再跑一次這支 migration，資料要搬到新欄位且不遺失。
    public function test_migration_converts_legacy_rows_without_losing_data(): void
    {
        // 退回「這支 migration 和它之後的所有 migration」（之後又新增了別的 migration，不能寫死只退一步）。
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($path) => basename($path))->sort()->values();
        $steps = $files->count() - $files->search('2026_10_15_100000_normalize_foreign_keys_and_duplicated_text_columns.php');
        Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]);

        $now = now();
        $teacher = DB::table('users')->insertGetId(['name' => '林老師', 'email' => 't@example.com', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
        $technician = DB::table('users')->insertGetId(['name' => '陳大成', 'email' => 'c@example.com', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
        $projector = DB::table('device_categories')->insertGetId(['name' => '投影機', 'created_at' => $now, 'updated_at' => $now]);

        // 舊格式的資料
        $planId = DB::table('maintenance_plans')->insertGetId(['name' => '計畫', 'device_category' => '投影機', 'cycle_days' => 30, 'start_date' => '2026-10-01', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $newCategoryPlan = DB::table('maintenance_plans')->insertGetId(['name' => '空調計畫', 'device_category' => '空調', 'cycle_days' => 30, 'start_date' => '2026-10-01', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $matchedItem = DB::table('maintenance_items')->insertGetId(['name' => '鏡頭清潔', 'category' => '投影機', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $genericItem = DB::table('maintenance_items')->insertGetId(['name' => '濾網清潔', 'category' => '電力設備', 'description' => '每月一次', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $orderId = DB::table('maintenance_orders')->insertGetId(['maintenance_plan_id' => $planId, 'device_category' => '投影機', 'source' => 'periodic', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('maintenance_results')->insert(['maintenance_order_id' => $orderId, 'result' => 'ok', 'executed_by' => '陳大成', 'executed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $orderTwo = DB::table('maintenance_orders')->insertGetId(['maintenance_plan_id' => $planId, 'source' => 'periodic', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('maintenance_results')->insert(['maintenance_order_id' => $orderTwo, 'result' => 'ng', 'executed_by' => '查無此人', 'notes' => '風扇異音', 'executed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

        $matchedCase = DB::table('repair_requests')->insertGetId(['title' => 't', 'description' => '舊資料', 'impact_level' => 'low', 'affects_class' => 0, 'status' => 'assigned', 'reporter_id' => $teacher, 'assignee_note' => '陳大成', 'created_at' => $now, 'updated_at' => $now]);
        $unmatchedCase = DB::table('repair_requests')->insertGetId(['title' => 't2', 'description' => '另一張', 'impact_level' => 'low', 'affects_class' => 0, 'status' => 'assigned', 'assignee_note' => '外部廠商', 'created_at' => $now, 'updated_at' => $now]);
        $classroom = DB::table('classrooms')->insertGetId(['campus' => 'A', 'building' => 'A', 'floor' => '1', 'room_code' => 'A101', 'room_name' => 'A101', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('classroom_reservations')->insert(['classroom_id' => $classroom, 'user_id' => $teacher, 'class_name' => '一年一班', 'reservation_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);

        Artisan::call('migrate', ['--force' => true]);

        // 設備類別：對得上的指向既有類別；計畫裡對不上的補一筆新類別；項目裡籠統的舊分類不污染類別主檔，寫進說明。
        $this->assertSame($projector, DB::table('maintenance_plans')->where('id', $planId)->value('device_category_id'));
        $airCondition = DB::table('device_categories')->where('name', '空調')->value('id');
        $this->assertNotNull($airCondition);
        $this->assertSame($airCondition, DB::table('maintenance_plans')->where('id', $newCategoryPlan)->value('device_category_id'));
        $this->assertSame($projector, DB::table('maintenance_items')->where('id', $matchedItem)->value('device_category_id'));
        $this->assertNull(DB::table('maintenance_items')->where('id', $genericItem)->value('device_category_id'));
        $this->assertFalse(DB::table('device_categories')->where('name', '電力設備')->exists());
        $this->assertStringContainsString('原分類：電力設備', DB::table('maintenance_items')->where('id', $genericItem)->value('description'));
        $this->assertStringContainsString('每月一次', DB::table('maintenance_items')->where('id', $genericItem)->value('description'));
        $this->assertSame($projector, DB::table('maintenance_orders')->where('id', $orderId)->value('device_category_id'));

        // 執行人：對得上帳號的轉成外鍵；對不上的姓名補寫進備註。
        $this->assertSame($technician, DB::table('maintenance_results')->where('maintenance_order_id', $orderId)->value('executed_by'));
        $unmatchedResult = DB::table('maintenance_results')->where('maintenance_order_id', $orderTwo)->first();
        $this->assertNull($unmatchedResult->executed_by);
        $this->assertStringContainsString('風扇異音', $unmatchedResult->note);
        $this->assertStringContainsString('查無此人', $unmatchedResult->note);

        // 報修單：維修人員姓名對應到帳號；對不上的寫進說明；報修人欄位改名後資料仍在。
        $matched = DB::table('repair_requests')->where('id', $matchedCase)->first();
        $this->assertSame($technician, $matched->assigned_to);
        $this->assertSame($teacher, $matched->requester_id);
        $unmatched = DB::table('repair_requests')->where('id', $unmatchedCase)->first();
        $this->assertNull($unmatched->assigned_to);
        $this->assertStringContainsString('外部廠商', $unmatched->description);
        $this->assertStringContainsString('另一張', $unmatched->description);

        // 預約申請人欄位改名後資料仍在。
        $this->assertSame($teacher, DB::table('classroom_reservations')->value('requester_id'));
    }
}
