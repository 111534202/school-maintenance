<?php

namespace Tests\Feature;

use App\Enums\RepairRequestStatus;
use App\Console\Commands\GenerateSchemaDocs;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 資料庫命名規範的自動檢查（規範全文與每個名稱的清單見 docs/資料庫命名規範與資料字典.md）。
 * 新增資料表或欄位時，名字不合規則，這裡會直接指出是哪一個；真的有理由例外，要在下面的例外清單寫明原因。
 */
class DatabaseNamingConventionTest extends TestCase
{
    use RefreshDatabase;

    // Laravel 內建的資料表（不屬於我們的業務資料，命名與欄位由框架決定）。
    private const FRAMEWORK_TABLES = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    // 例外：資料表名稱不是複數（knowledge_base 依規格文件固定為單數）。
    private const SINGULAR_TABLE_EXCEPTIONS = ['knowledge_base'];

    // 例外：布林欄位不用 is_／has_ 開頭（目前沒有例外）。
    private const BOOLEAN_EXCEPTIONS = [];

    // 例外：日期欄位不是 _date 結尾（目前沒有例外）。
    private const DATE_EXCEPTIONS = [];

    // 例外：<名詞>_name 只能用在「沒有自己資料表的另一個東西」的名稱，目前只有預約的班級名稱。其他地方的稱呼一律叫 name。
    // attachments.file_name：附件記錄的就是一個檔案，檔名和 file_path（路徑）成對，沿用規格書名稱。
    private const NAMED_THING_EXCEPTIONS = ['classroom_reservations.class_name', 'attachments.file_name'];

    // 例外：<名詞>_code 的名詞不是自己這張表的實體，而是這個實體身上「另一種編號」所屬的東西（資產編號 asset_code）。
    private const OTHER_CODE_EXCEPTIONS = ['devices.asset_code'];

    // 已經被取代的舊名稱：不准再出現（防止有人用舊名稱新增欄位）。
    private const RETIRED_NAMES = [
        'slug', 'username', 'room_code', 'room_name', 'room_type', 'reporter_id', 'assignee_note',
        'original_name', 'disk_path', 'warranty_until', 'affects_class', 'device_category',
    ];

    // 例外：多型關聯與框架表的編號欄位沒有外鍵（見 DatabaseNormalizationTest）。
    private const NAME_PREFIX_EXCEPTIONS = [];

    /** @return list<string> 我們自己的業務資料表 */
    private function businessTables(): array
    {
        return array_values(array_filter(
            array_map(fn ($table) => $table['name'], Schema::getTables()),
            fn ($name) => ! in_array($name, self::FRAMEWORK_TABLES, true)
        ));
    }

    public function test_table_names_are_lowercase_snake_case_plurals(): void
    {
        foreach ($this->businessTables() as $table) {
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $table, "資料表 {$table} 要用小寫英文加底線");

            if (! in_array($table, self::SINGULAR_TABLE_EXCEPTIONS, true)) {
                $this->assertSame(Str::plural(Str::singular($table)), $table, "資料表 {$table} 要用複數名詞");
            }
        }
    }

    // 資料表名稱公式：［領域詞］＿實體詞（複數）。有底線的表，前半段一定是領域詞清單裡的詞（清單見文件 1.5 節）。
    public function test_table_names_start_with_a_known_domain_word(): void
    {
        $domainWords = array_keys(GenerateSchemaDocs::DOMAIN_WORDS);

        foreach ($this->businessTables() as $table) {
            if (in_array($table, self::SINGULAR_TABLE_EXCEPTIONS, true) || ! str_contains($table, '_')) {
                continue;   // 單一名詞的核心主檔不需要領域詞
            }

            $firstWord = explode('_', $table)[0];
            $this->assertContains($firstWord, $domainWords, "資料表 {$table} 的領域詞「{$firstWord}」不在清單裡；若是新的業務領域，請先加進 GenerateSchemaDocs::DOMAIN_WORDS 並重新產生文件");
        }
    }

    public function test_column_names_are_lowercase_snake_case_and_avoid_banned_words(): void
    {
        // 不准用的名稱：單獨叫 code（要寫成 <實體>_code）、複數的 notes／remarks（備註一律單數 note）。
        $banned = array_merge(['code', 'notes', 'remarks', 'remark', 'comment', 'comments', 'desc'], self::RETIRED_NAMES);

        foreach ($this->businessTables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $column, "{$table}.{$column} 要用小寫英文加底線");
                $this->assertNotContains($column, $banned, "{$table}.{$column} 不能叫這個名字（見 docs/資料庫命名規範與資料字典.md）");
            }
        }
    }

    // 稱呼一律叫 name：除了例外清單，不准出現 xxx_name（例如 room_name、dept_name）。
    public function test_names_are_called_name(): void
    {
        foreach ($this->businessTables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (str_ends_with($column, '_name') && ! in_array("{$table}.{$column}", self::NAMED_THING_EXCEPTIONS, true)) {
                    $this->fail("{$table}.{$column}：稱呼一律叫 name（見 docs/資料庫命名規範與資料字典.md 規則 N8）");
                }
            }
        }

        $this->assertTrue(true);
    }

    // 業務代碼一律叫 <實體>_code，而且前綴要是自己這張表的實體（classrooms.classroom_code、roles.role_code…）。
    public function test_business_codes_are_named_after_their_own_entity(): void
    {
        $expected = [
            'classrooms' => 'classroom_code', 'departments' => 'department_code', 'devices' => 'device_code', 'roles' => 'role_code',
        ];

        foreach ($expected as $table => $column) {
            $this->assertContains($column, Schema::getColumnListing($table), "{$table} 的業務代碼應該叫 {$column}");
        }

        foreach ($this->businessTables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (str_ends_with($column, '_code') && ! in_array("{$table}.{$column}", self::OTHER_CODE_EXCEPTIONS, true)) {
                    $this->assertSame(Str::singular($table) . '_code', $column, "{$table}.{$column} 的代碼前綴要是自己的實體（" . Str::singular($table) . '_code）');
                }
            }
        }
    }

    public function test_every_business_table_has_the_standard_timestamps(): void
    {
        foreach ($this->businessTables() as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertContains('created_at', $columns, "{$table} 缺少 created_at");
            $this->assertContains('updated_at', $columns, "{$table} 缺少 updated_at");
        }
    }

    // 型態決定名稱：布林 is_／has_、時間 _at、日期 _date、時刻 _time。
    public function test_the_column_type_decides_the_name_suffix(): void
    {
        foreach ($this->businessTables() as $table) {
            foreach (Schema::getColumns($table) as $col) {
                $name = $col['name'];
                $key = "{$table}.{$name}";

                if (str_contains($col['type'], 'tinyint(1)') && ! in_array($key, self::BOOLEAN_EXCEPTIONS, true)) {
                    $this->assertMatchesRegularExpression('/^(is|has)_[a-z_]+$/', $name, "{$key} 是布林欄位，名稱要用 is_ 或 has_ 開頭");
                }

                if (in_array($col['type_name'], ['timestamp', 'datetime'], true)) {
                    $this->assertStringEndsWith('_at', $name, "{$key} 是日期時間欄位，名稱要用 _at 結尾");
                }

                if ($col['type_name'] === 'date' && ! in_array($key, self::DATE_EXCEPTIONS, true)) {
                    $this->assertStringEndsWith('_date', $name, "{$key} 是日期欄位，名稱要用 _date 結尾");
                }

                if ($col['type_name'] === 'time') {
                    $this->assertStringEndsWith('_time', $name, "{$key} 是時刻欄位，名稱要用 _time 結尾");
                }
            }
        }
    }

    // 狀態：狀態機一律叫 status；某一方面的狀態叫 <方面>_status。
    public function test_status_columns_are_named_status_or_aspect_status(): void
    {
        foreach ($this->businessTables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (str_contains($column, 'status')) {
                    $this->assertMatchesRegularExpression('/^([a-z]+(_[a-z]+)*_)?status$/', $column, "{$table}.{$column} 狀態欄位要叫 status 或 <方面>_status");
                }
            }
        }
    }

    // 外鍵：指向使用者的有四種寫法（提出者／管理人／被指派者／操作人／動作的人 *_by）；指向其他資料表的一律是「<該表單數>_id」。
    public function test_foreign_key_names_follow_the_rules(): void
    {
        foreach ($this->businessTables() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                foreach ($foreignKey['columns'] as $column) {
                    $target = $foreignKey['foreign_table'];

                    if ($target === 'users') {
                        $this->assertMatchesRegularExpression('/^(requester_id|manager_id|assigned_to|user_id)$|_by$/', $column, "{$table}.{$column} 指向 users，名稱要是 requester_id／manager_id／assigned_to／user_id 或 *_by");
                    } else {
                        $this->assertSame(Str::singular($target) . '_id', $column, "{$table}.{$column} 指向 {$target}，名稱要是 " . Str::singular($target) . '_id');
                    }
                }
            }
        }
    }

    // 狀態值與類型值：小寫英文加底線（畫面顯示的中文走語言檔，不寫進資料庫）。
    public function test_stored_codes_are_lowercase_snake_case(): void
    {
        $codes = array_merge(
            array_map(fn ($case) => $case->value, RepairRequestStatus::cases()),
            Device::STATUSES,
            [
                MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS, MaintenanceOrder::STATUS_COMPLETED,
                MaintenanceOrder::SOURCE_PERIODIC, MaintenanceOrder::SOURCE_AI,
                MaintenanceResult::RESULT_OK, MaintenanceResult::RESULT_NG,
                MaintenanceResult::NG_CONVERSION_PENDING, MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, MaintenanceResult::NG_CONVERSION_CONVERTED,
                PreventiveCandidate::STATUS_PENDING, PreventiveCandidate::STATUS_APPROVED, PreventiveCandidate::STATUS_REJECTED,
                PreventiveCandidate::STATUS_AUTO_CREATED, PreventiveCandidate::STATUS_DUPLICATE,
            ]
        );

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $code, "代碼 {$code} 要用小寫英文加底線");
        }
    }
}
