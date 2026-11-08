<?php

namespace App\Support;

/**
 * 「先預覽、勾選區塊與欄位再匯出 Excel／PDF」的共用工具（成本分析報表、零件履歷）。
 *
 * 區塊資料格式：區塊代號 => [
 *     'title' => 區塊標題,
 *     'headings' => 欄位名稱（依顯示順序）,
 *     'rows' => 每一列的值（數字保留成數字，Excel 才能計算）,
 *     'numeric' => 靠右對齊（數字）的欄位索引,
 * ]
 */
class ExportSections
{
    /**
     * 只留下勾選的區塊與欄位（$columns：區塊代號 => 欄位索引）。
     *
     * @param  array<int, string>  $keys
     * @param  array<string, array<int, int|string>>  $columns
     */
    public static function pick(array $sections, array $keys, array $columns): array
    {
        $picked = [];

        foreach ($sections as $key => $section) {
            if (! in_array($key, $keys, true)) {
                continue;
            }

            $indexes = array_values(array_filter(
                array_map('intval', $columns[$key] ?? []),
                fn (int $i) => array_key_exists($i, $section['headings'])
            ));
            sort($indexes);
            if (! $indexes) {
                continue;
            }

            $picked[$key] = [
                'title' => $section['title'],
                'headings' => array_map(fn ($i) => $section['headings'][$i], $indexes),
                'rows' => array_map(fn ($row) => array_map(fn ($i) => $row[$i] ?? '', $indexes), $section['rows']),
                'numeric' => array_keys(array_filter($indexes, fn ($i) => in_array($i, $section['numeric'], true))),
            ];
        }

        return $picked;
    }

    /** 畫面與 PDF 的顯示文字：數字加千分位（最多兩位小數），其他照原樣。 */
    public static function display(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        }

        return (string) $value;
    }
}
