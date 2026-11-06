<?php

namespace App\Exports;

use App\Models\Part;
use App\Support\PartExportColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 零件清單 Excel（零件庫存列表最下面的「匯出 Excel／PDF」）：只含預覽視窗裡勾選的零件與欄位。
 */
class PartsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @param  Collection<int, Part>  $parts
     * @param  array<string, string>  $columns  欄位代號 => 欄位名稱
     */
    public function __construct(private Collection $parts, private array $columns)
    {
    }

    public function title(): string
    {
        return '零件清單';
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function array(): array
    {
        return $this->parts
            ->map(fn (Part $part) => array_values(array_intersect_key(PartExportColumns::values($part), $this->columns)))
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
