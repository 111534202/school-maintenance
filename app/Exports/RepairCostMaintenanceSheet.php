<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** 成本分析 Excel 的「保養用料明細」工作表：保養時用掉的零件，單價是扣料當下那一批的進貨單價。 */
class RepairCostMaintenanceSheet implements FromArray, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '保養用料明細';
    }

    public function headings(): array
    {
        return ['保養日期', '保養工單', '設備', '教室／地點', '備品名稱', '數量', '歷史單價', '材料費小計'];
    }

    public function array(): array
    {
        return ($this->data['maintenanceUsages'] ?? collect())
            ->map(function ($usage) {
                $order = $usage->maintenanceResult?->maintenanceOrder;
                $device = $order?->device;
                $classroom = $device?->classroom;
                $unitPrice = $usage->unit_price;

                return [
                    $usage->maintenanceResult?->executed_at?->format('Y-m-d H:i:s') ?? '',
                    $order?->displayNumber() ?? '工單資料不存在',
                    $device ? ($device->device_code ?: $device->asset_code ?: '設備未編碼') : '未關聯設備',
                    $classroom ? trim(($classroom->name ?? '') . ' ' . ($classroom->classroom_code ?? '')) : '未記錄地點',
                    $usage->part->name ?? '零件資料不存在',
                    (int) $usage->quantity,
                    $unitPrice === null ? '缺少單價' : (float) $unitPrice,
                    $unitPrice === null ? '缺少單價' : (float) ($usage->quantity * $unitPrice),
                ];
            })
            ->values()
            ->all();
    }
}
