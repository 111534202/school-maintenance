<?php

namespace App\Support;

use App\Models\MaintenanceOrder;

/**
 * 保養工單匯出的欄位：欄位代號 => 欄位名稱（依顯示順序），含保養結果（OK／NG、執行人、轉報修單號）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選，預覽與 Excel 用同一份資料（values）。
 */
class MaintenanceOrderExportColumns
{
    public const COLUMNS = [
        'number' => '工單編號',
        'device_code' => '設備編號',
        'device' => '設備（類別 品牌 型號）',
        'category' => '設備類別',
        'plan' => '來源計畫',
        'source' => '來源',
        'status' => '狀態',
        'assignee' => '負責人',
        'scheduled_date' => '排定保養日期',
        'overdue' => '逾期',
        'result' => '保養結果',
        'executor' => '執行人',
        'executed_at' => '執行時間',
        'note' => '說明',
        'repair_request_id' => '轉報修單號',
        'created_at' => '建立時間',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = [];

    /**
     * @param  list<string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    public static function label(MaintenanceOrder $o): string
    {
        return $o->displayNumber();
    }

    /**
     * 一張工單的每個欄位值。呼叫前要先載入 maintenancePlan、result.executor、deviceCategory、device.category、assignee。
     *
     * @return array<string, scalar|null>
     */
    public static function values(MaintenanceOrder $o): array
    {
        $result = $o->result;

        return [
            'number' => $o->displayNumber(),
            'device_code' => $o->device?->device_code,
            'device' => $o->device ? trim(($o->device->category?->name ?? '') . ' ' . $o->device->brand . ' ' . $o->device->model) : null,
            'category' => $o->categoryName(),
            'plan' => $o->maintenancePlan?->name ?? ($o->source === MaintenanceOrder::SOURCE_AI ? 'AI 預防保養（無計畫）' : null),
            'source' => $o->sourceLabel(),
            'status' => $o->statusLabel()[0],
            'assignee' => $o->assignee?->name,
            'scheduled_date' => $o->scheduled_date?->format('Y-m-d'),
            'overdue' => $o->isOverdue() ? '是' : '否',
            'result' => $result ? ($result->isNg() ? 'NG 異常' : 'OK 正常') : null,
            'executor' => $result?->executor?->name,
            'executed_at' => $result?->executed_at?->format('Y-m-d H:i'),
            'note' => $result?->note,
            // NG 每台設備各一張報修單：列出全部單號（#12、#13）。
            'repair_request_id' => $result?->repairRequests->map(fn ($repair) => '#' . $repair->id)->implode('、') ?: null,
            'created_at' => $o->created_at?->format('Y-m-d H:i'),
        ];
    }
}
