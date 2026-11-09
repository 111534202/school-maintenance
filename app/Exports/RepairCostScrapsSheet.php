<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** 成本分析 Excel 的「報廢明細」工作表（篩選選「顯示報廢損失」時才有）。 */
class RepairCostScrapsSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '報廢明細';
    }

    public function headings(): array
    {
        return ['報廢日期', '零件編號', '零件名稱', '原因', '數量', '單位', '損失金額', '登記人', '備註'];
    }

    public function array(): array
    {
        return $this->data['scraps']
            ->map(fn ($scrap) => [
                $scrap->scrap_date->format('Y-m-d'),
                $scrap->part?->part_code ?? '',
                $scrap->part?->name ?? '零件資料不存在',
                $scrap->reason,
                $scrap->quantity,
                $scrap->part?->unit ?? '',
                (float) $scrap->lossAmount(),
                $scrap->recorder?->name ?? '',
                $scrap->note ?? '',
            ])
            ->values()
            ->all();
    }
}
