<?php

namespace App\Support;

use App\Models\Part;
use Illuminate\Support\Collection;

/**
 * 零件履歷「匯出 Excel／PDF」的區塊：基本資料、使用紀錄、採購紀錄、報廢紀錄（內容與零件履歷頁面上看到的相同）。
 * 格式見 ExportSections；數量帶單位（例如「15 條」），金額保留成數字。
 */
class PartHistorySections
{
    /**
     * @param  Collection<int, array<string, mixed>>  $usages  PartController::history() 整理好的使用紀錄
     */
    public static function build(Part $part, Collection $usages, Collection $purchases, Collection $scraps): array
    {
        $basic = PartExportColumns::values($part);

        return [
            'basic' => [
                'title' => '基本資料',
                'headings' => array_values(PartExportColumns::COLUMNS),
                'rows' => [array_values($basic)],
                'numeric' => [4, 5, 6],
            ],
            'usages' => [
                'title' => '使用紀錄',
                'headings' => ['來源', '使用日期', '狀態', '單號', '執行人', '數量', '單價', '小計', '扣用批次'],
                'rows' => $usages->map(fn (array $u) => [
                    $u['source'],
                    $u['used_at']?->format('Y-m-d') ?? '—',
                    $u['status'] ?? '—',
                    $u['reference_label'],
                    $u['executor'] ?? '—',
                    $part->withUnit($u['quantity']),
                    $u['unit_price'] === null ? '缺少單價' : (float) $u['unit_price'],
                    $u['unit_price'] === null ? '—' : (float) ($u['quantity'] * $u['unit_price']),
                    $u['batch'] ? $u['batch']->purchase_date->format('Y-m-d') . ' 採購' : '—',
                ])->values()->all(),
                'numeric' => [5, 6, 7],
            ],
            'purchases' => [
                'title' => '採購紀錄',
                'headings' => ['採購日期', '廠商', '數量', '單價', '總價', '剩餘數量', '備註'],
                'rows' => $purchases->map(fn ($p) => [
                    $p->purchase_date->format('Y-m-d'),
                    $p->vendor_name ?? '—',
                    $part->withUnit($p->quantity),
                    (float) $p->unit_price,
                    (float) $p->totalPrice(),
                    $part->withUnit($p->remaining_quantity),
                    $p->note ?? '—',
                ])->values()->all(),
                'numeric' => [2, 3, 4, 5],
            ],
            'scraps' => [
                'title' => '報廢紀錄',
                // 跟零件履歷畫面上的報廢紀錄同樣順序。
                'headings' => ['報廢日期', '登記人', '數量', '損失金額', '扣用批次', '原因', '備註'],
                'rows' => $scraps->map(fn ($s) => [
                    $s->scrap_date->format('Y-m-d'),
                    $s->recorder?->name ?? '—',
                    $part->withUnit($s->quantity),
                    (float) $s->lossAmount(),
                    $s->batches->map(fn ($b) => ($b->purchase ? $b->purchase->purchase_date->format('Y-m-d') . ' 採購' : '—') . ' × ' . $b->quantity)->join('、') ?: '—',
                    $s->reason,
                    $s->note ?? '—',
                ])->values()->all(),
                'numeric' => [2, 3],
            ],
        ];
    }
}
