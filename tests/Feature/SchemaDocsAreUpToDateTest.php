<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 資料庫命名規範與資料字典（docs/資料庫命名規範與資料字典.md）是從資料庫結構自動產生的。
 * 改了資料表或欄位卻忘了更新文件，這個測試會失敗；解法：php artisan docs:schema
 */
class SchemaDocsAreUpToDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_generated_docs_match_the_current_database_structure(): void
    {
        $this->artisan('docs:schema', ['--check' => true])->assertExitCode(0);
    }

    // 每張資料表都要在 GenerateSchemaDocs 補上中文名稱與正規化結論（文件裡才不會出現沒說明的表）。
    public function test_every_table_has_a_chinese_name_and_a_normalization_verdict(): void
    {
        $this->artisan('docs:schema', ['--check' => true])->run();
        $doc = file_get_contents(base_path('docs/資料庫命名規範與資料字典.md'));

        $this->assertStringNotContainsString('尚未補上結論', $doc);
        $this->assertStringNotContainsString('（未分類）', $doc, '有資料表還沒補上類型與命名拆解（GenerateSchemaDocs::$tableNaming）');
    }
}
