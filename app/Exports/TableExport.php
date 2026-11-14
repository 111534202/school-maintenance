<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 一張工作表的 Excel 匯出（報修單、保養工單、空間申請的列表匯出共用）：
 * 第一列是粗體標題列，欄寬自動調整；資料由各控制器依「目前的搜尋與篩選條件」組好交進來。
 */
class TableExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @param  string  $title  工作表名稱
     * @param  list<string>  $headings  欄位標題
     * @param  list<list<scalar|null>>  $rows  資料列（每列的欄數要和標題一樣）
     */
    public function __construct(private string $title, private array $headings, private array $rows)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
