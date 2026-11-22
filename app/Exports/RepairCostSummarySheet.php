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
                '維修用料＋保養用料；只計算有歷史單價的用料明細（維修 ' . number_format((float) ($this->data['repairSubtotal'] ?? $this->data['knownSubtotal'])) . '、保養 ' . number_format((float) ($this->data['maintenanceSubtotal'] ?? 0)) . '）',
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
            // 報廢損失放在最後（前面幾列的位置不變）。
            ...(($this->data['includeScraps'] ?? false) ? [
                [
                    '報廢損失',
                    (float) $this->data['scrapLoss'],
                    '依報廢日期篩選，共 ' . $this->data['scrapCount'] . ' 筆；金額＝各批數量×那一批的單價',
                ],
                [
                    '材料費＋報廢損失',
                    (float) $this->data['totalCost'],
                    '已知材料費小計加上報廢損失',
                ],
            ] : []),
            // 進貨成本放在最後（前面幾列的位置不變）；進貨是買進來、材料費是用掉了，兩者不相加。
            [
                '進貨成本',
                (float) ($this->data['purchaseTotal'] ?? 0),
                '依進貨日期篩選，共 ' . ($this->data['purchaseCount'] ?? 0) . ' 批，其中期初庫存 NT$ ' . number_format((float) ($this->data['purchaseOpeningTotal'] ?? 0)) . '；不併入上面的材料費合計',
            ],
        ];
    }
}