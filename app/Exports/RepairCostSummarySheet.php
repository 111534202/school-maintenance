<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RepairCostSummarySheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private array $data
    ) {}

    public function title(): string
    {
        return '成本總覽';
    }

    public function headings(): array
    {
        return ['統計項目', '數值', '說明'];
    }

    public function array(): array
    {
        return [
            [
                '已知材料費小計',
                (float) $this->data['knownSubtotal'],
                '只計算有歷史單價的用料明細',
            ],
            [
                '缺少單價的明細數',
                (int) $this->data['missingPriceCount'],
                '缺價明細不計入已知材料費小計',
            ],
            [
                '涉及維修案件數',
                (int) $this->data['repairRequestCount'],
                '同一案件只計算一次',
            ],
            [
                '開始日期',
                $this->data['startDate'] ?? '不限',
                '依維修紀錄建立日期篩選',
            ],
            [
                '結束日期',
                $this->data['endDate'] ?? '不限',
                '依維修紀錄建立日期篩選',
            ],
            [
                '案件狀態',
                $this->data['status'] ?? '全部狀態',
                '依目前頁面的狀態篩選條件',
            ],
        ];
    }
}