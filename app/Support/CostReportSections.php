<?php

namespace App\Support;

use App\Enums\RepairRequestStatus;
use App\Exports\RepairCostDetailsSheet;
use App\Exports\RepairCostPartsSheet;
use App\Exports\RepairCostScrapsSheet;
use App\Exports\RepairCostSummarySheet;

/**
 * 成本分析報表「匯出 Excel／PDF」的各區塊與欄位。
 * 資料直接沿用原本完整匯出的四張工作表（RepairCost*Sheet），所以預覽視窗、只匯出勾選欄位的 Excel、PDF 三邊數字一致。
 */
class CostReportSections
{
    /** 區塊代號 => [標題, 工作表類別, 靠右對齊（數字）的欄位索引]。 */
    private const SECTIONS = [
        'summary' => ['成本總覽', RepairCostSummarySheet::class, [1]],
        'parts' => ['備品使用彙總', RepairCostPartsSheet::class, [1, 2, 3]],
        'details' => ['材料費明細', RepairCostDetailsSheet::class, [1, 2, 8, 9, 10]],
        'scraps' => ['報廢明細', RepairCostScrapsSheet::class, [4, 6]],
    ];

    /**
     * 依目前的篩選結果建立各區塊（篩選「報廢損失：不顯示」時沒有報廢明細）。
     *
     * @return array<string, array{title: string, headings: list<string>, rows: list<list<mixed>>, numeric: list<int>}>
     */
    public static function build(array $data): array
    {
        $sections = [];

        foreach (self::SECTIONS as $key => [$title, $sheetClass, $numeric]) {
            if ($key === 'scraps' && ! ($data['includeScraps'] ?? false)) {
                continue;
            }

            $sheet = new $sheetClass($data);
            $headings = $sheet->headings();
            $rows = $sheet->array();

            // 材料費明細的「案件狀態」在工作表裡是英文代碼（completed），匯出時換成中文。
            $statusIndex = array_search('案件狀態', $headings, true);
            if ($statusIndex !== false) {
                foreach ($rows as &$row) {
                    $row[$statusIndex] = RepairRequestStatus::tryFrom((string) $row[$statusIndex])?->label() ?? $row[$statusIndex];
                }
                unset($row);
            }

            $sections[$key] = ['title' => $title, 'headings' => $headings, 'rows' => $rows, 'numeric' => $numeric];
        }

        return $sections;
    }

    /** 只留下勾選的區塊與欄位（見 ExportSections::pick）。 */
    public static function pick(array $sections, array $keys, array $columns): array
    {
        return ExportSections::pick($sections, $keys, $columns);
    }

    /** 顯示文字（見 ExportSections::display）。 */
    public static function display(mixed $value): string
    {
        return ExportSections::display($value);
    }
}
