<?php

namespace App\Support;

use App\Models\ClassroomReservation;

/**
 * 空間申請匯出的欄位：欄位代號 => 欄位名稱（依顯示順序）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選要匯出哪些，預覽與 Excel 用同一份資料（values）。
 */
class ReservationExportColumns
{
    public const COLUMNS = [
        'id' => '申請編號',
        'class_name' => '申請班級',
        'requester' => '申請人',
        'classroom' => '教室',
        'slots' => '申請時段',
        'slot_count' => '節數',
        'purpose' => '用途',
        'status' => '申請狀態',
        'all_or_nothing' => '其中一節不能借就整筆不要',
        'reviewer' => '審核人',
        'reviewed_at' => '審核時間',
        'rejection_reason' => '拒絕原因',
        'created_at' => '送出時間',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = ['id', 'slot_count'];

    /**
     * 依使用者勾選的欄位代號取出欄位；沒勾任何欄位（或代號都不存在）就給全部。
     *
     * @param  list<string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    /**
     * 一筆申請的每個欄位值（欄位代號 => 值）。呼叫前要先載入 requester、classroom、slots、reviewer，避免每一列多查一次資料庫。
     *
     * @return array<string, scalar|null>
     */
    public static function values(ClassroomReservation $r): array
    {
        [$slotText, $slotCount] = $r->slotSummary();

        return [
            'id' => $r->id,
            'class_name' => $r->class_name,
            'requester' => $r->requester?->name,
            'classroom' => $r->classroom?->name,
            'slots' => $slotText,
            'slot_count' => $slotCount,
            'purpose' => $r->purpose,
            'status' => $r->statusLabel()[0],
            'all_or_nothing' => $r->is_all_or_nothing ? '是' : '否',
            'reviewer' => $r->reviewer->name ?? ($r->isReviewed() ? '系統自動' : null),
            'reviewed_at' => $r->reviewed_at?->format('Y-m-d H:i'),
            'rejection_reason' => $r->rejection_reason,
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
        ];
    }
}
