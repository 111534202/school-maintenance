<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RepairCostDetailsSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '用料明細';
    }

    public function headings(): array
    {
        return [
            '維修日期',
            '案件編號',
            '維修紀錄編號',
            '維修案件',
            '案件狀態',
            '設備',
            '教室／地點',
            '備品名稱',
            '數量',
            '歷史單價',
            '材料費小計',
        ];
    }

    public function array(): array
    {
        return $this->data['partUsages']
            ->map(function ($usage) {
                $repairRequest = $usage->repairLog?->repairRequest;
                $device = $repairRequest?->device;
                $classroom = $repairRequest?->classroom ?? $device?->classroom;   // 報修單填的地點優先，沒有才用設備所在教室

                $deviceLabel = $device
                    ? ($device->device_code ?: $device->asset_code ?: '設備未編碼')
                    : ($repairRequest?->device_note ?: '未關聯設備');

                $locationLabel = $classroom
                    ? trim(($classroom->name ?? '') . ' ' . ($classroom->classroom_code ?? ''))
                    : '未記錄地點';

                $unitPrice = $usage->unit_price;

                return [
                    $usage->repairLog?->created_at?->format('Y-m-d H:i:s') ?? '',
                    $repairRequest?->id ?? '',
                    $usage->repair_log_id,
                    $repairRequest?->title ?? '報修案件不存在',
                    $repairRequest?->status?->value ?? '',
                    $deviceLabel,
                    $locationLabel,
                    $usage->part?->name ?? '零件資料不存在',
                    (int) $usage->quantity,
                    $unitPrice === null ? '缺少單價' : (float) $unitPrice,
                    $unitPrice === null
                        ? '缺少單價'
                        : (float) ($usage->quantity * $unitPrice),
                ];
            })
            ->values()
            ->all();
    }
}