<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RepairCostPartsSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '備品彙總';
    }

    public function headings(): array
    {
        return [
            '備品名稱',
            '使用總數量',
            '已知材料費小計',
            '缺少單價明細數',
        ];
    }

    public function array(): array
    {
        return $this->data['partUsageSummary']
            ->map(fn ($item) => [
                $item->part?->name ?? '零件資料不存在',
                (int) $item->total_quantity,
                (float) $item->known_subtotal,
                (int) $item->missing_price_count,
            ])
            ->values()
            ->all();
    }
}