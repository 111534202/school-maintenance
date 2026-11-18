<?php

namespace App\Support;

use App\Models\RepairRequest;

/**
 * 報修單匯出的欄位：欄位代號 => 欄位名稱（依顯示順序）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選，預覽與 Excel 用同一份資料（values）。
 */
class RepairExportColumns
{
    public const COLUMNS = [
        'id' => '編號',
        'title' => '報修標題',
        'status' => '狀態',
        'impact' => '影響程度',
        'class_affected' => '影響上課',
        'device_code' => '設備編號',
        'device' => '設備（類別 品牌 型號）',
        'device_note' => '設備位置／描述',
        'location' => '設備地點',
        'requester' => '報修人',
        'technician' => '維修人員',
        'scheduled_at' => '預計處理時間',
        'created_at' => '送出時間',
        'description' => '故障描述',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = ['id'];

    private const IMPACT = ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'];

    /**
     * @param  list<string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    public static function label(RepairRequest $r): string
    {
        return '#' . $r->id . ' ' . $r->title;
    }

    /**
     * 一張報修單的每個欄位值。呼叫前要先載入 requester、device.category、assignedTechnician。
     *
     * @return array<string, scalar|null>
     */
    public static function values(RepairRequest $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'status' => $r->status->label(),
            'impact' => self::IMPACT[$r->impact_level] ?? $r->impact_level,
            'class_affected' => $r->is_class_affected ? '是' : '否',
            'device_code' => $r->device?->device_code,
            'device' => $r->device ? trim(($r->device->category?->name ?? '') . ' ' . $r->device->brand . ' ' . $r->device->model) : null,
            'device_note' => $r->device_note,
            'location' => $r->locationLabel(),
            'requester' => $r->requester?->name,
            'technician' => $r->assignedTechnician?->name,
            'scheduled_at' => $r->scheduled_at?->format('Y-m-d H:i'),
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
            'description' => $r->description,
        ];
    }
}
