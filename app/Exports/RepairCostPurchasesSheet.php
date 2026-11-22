<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** 成本分析 Excel 的「進貨明細」工作表：期間內每一批入庫的金額（含期初庫存，備註欄會標示）。 */
class RepairCostPurchasesSheet implements FromArray, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '進貨明細';
    }

    public function headings(): array
    {
        return ['進貨日期', '零件編號', '零件名稱', '廠商', '數量', '單位', '單價', '進貨金額', '備註'];
    }

    public function array(): array
    {
        return ($this->data['purchases'] ?? collect())
            ->map(fn ($purchase) => [
                $purchase->purchase_date->format('Y-m-d'),
                $purchase->part->part_code ?? '',
                $purchase->part->name ?? '零件資料不存在',
                $purchase->vendor_name ?? '',
                $purchase->quantity,
                $purchase->part->unit,
                (float) $purchase->unit_price,
                (float) $purchase->totalPrice(),
                $purchase->note ?? '',
            ])
            ->values()
            ->all();
    }
}
