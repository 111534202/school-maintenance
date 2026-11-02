<?php

namespace App\Exports;

use App\Models\Part;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * 目前低庫存品項的 Excel（零件庫存列表的「查看目前低庫存品項」視窗匯出）。
 * 欄位和視窗相同；最後多一欄「建議補貨數量」＝安全庫存－目前庫存，方便直接拿去採購。
 */
class LowStockPartsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    /** @param  Collection<int, Part>  $parts */
    public function __construct(private Collection $parts)
    {
    }

    public function title(): string
    {
        return '低庫存品項';
    }

    public function headings(): array
    {
        return ['零件編號', '名稱', '規格', '品牌／型號', '目前庫存', '安全庫存', '單位', '建議補貨數量'];
    }

    public function array(): array
    {
        return $this->parts
            ->map(fn (Part $part) => [
                $part->part_code,
                $part->name,
                $part->specification ?? '',
                $part->brandModel() ?? '',
                (int) $part->current_stock,
                (int) $part->safety_stock,
                $part->unit,
                max(0, $part->safety_stock - $part->current_stock),
            ])
            ->values()
            ->all();
    }
}
