<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 成本分析報表：只匯出預覽視窗裡勾選的區塊與欄位（每個區塊一張工作表）。
 * 沒有勾選任何東西時（直接開匯出網址）仍走原本的完整匯出 RepairCostAnalysisExport。
 */
class CostReportSelectedExport implements Export, WithMultipleSheets
{
    /** @param array<string, array{title: string, headings: list<string>, rows: list<list<mixed>>, numeric: list<int>}> $sections */
    public function __construct(private array $sections)
    {
    }

    public function sheets(): array
    {
        return array_values(array_map(
            fn (array $section) => new CostReportSelectedSheet($section['title'], $section['headings'], $section['rows']),
            $this->sections
        ));
    }
}
