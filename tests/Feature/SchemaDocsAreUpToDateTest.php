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

    // 資料字典裡每個欄位都要有中文說明（新增欄位時到 GenerateSchemaDocs 的 $columnText 補上）。
    public function test_every_column_in_the_dictionary_has_a_description(): void
    {
        $doc = file_get_contents(base_path('docs/資料庫命名規範與資料字典.md'));
        $section = substr($doc, strpos($doc, '## 7. 完整名稱清單與資料字典'), strpos($doc, '## 8. ') - strpos($doc, '## 7. 完整名稱清單與資料字典'));

        $blank = [];
        foreach (explode("\n", $section) as $line) {
            // 欄位列：| `欄位` | 類別 | 型態 | 必填 | 唯一 | 外鍵 | 說明 |；最後一欄（說明）是空的就記下來。
            if (preg_match('/^\| `([a-z_]+)` \|.*\|\s*\|$/u', $line, $m) && substr_count($line, '|') === 8) {
                $blank[] = $m[1];
            }
        }

        $this->assertSame([], $blank, '這些欄位沒有中文說明（GenerateSchemaDocs::$columnText／$columnTextSpecific）：' . implode('、', array_unique($blank)));
    }
}
