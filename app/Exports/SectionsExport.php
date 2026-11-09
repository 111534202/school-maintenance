<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 「勾選區塊與欄位」的 Excel 匯出（每個區塊一張工作表）：成本分析報表、零件履歷共用。
 * 區塊資料格式見 App\Support\ExportSections。
 */
class SectionsExport implements Export, WithMultipleSheets
{
    /** @param array<string, array{title: string, headings: list<string>, rows: list<list<mixed>>, numeric: list<int>}> $sections */
    public function __construct(private array $sections)
    {
    }

    public function sheets(): array
    {
        return array_values(array_map(
            fn (array $section) => new SectionsSheet($section['title'], $section['headings'], $section['rows']),
            $this->sections
        ));
    }
}
