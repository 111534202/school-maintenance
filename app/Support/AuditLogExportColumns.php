<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Services\AuditLogger;

/**
 * 操作紀錄匯出的欄位（不含變更內容，可能有敏感資料）：欄位代號 => 欄位名稱（依顯示順序）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選，預覽與 Excel 用同一份資料（values）。
 */
class AuditLogExportColumns
{
    public const COLUMNS = [
        'created_at' => '時間',
        'user' => '操作者',
        'action' => '事件',
        'type' => '對象類型',
        'loggable_id' => '對象編號',
        'description' => '說明',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = ['loggable_id'];

    /**
     * @param  list<string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    public static function label(AuditLog $log): string
    {
        return $log->created_at?->format('Y-m-d H:i:s') . ' ' . $log->description;
    }

    /**
     * 一筆紀錄的每個欄位值。呼叫前要先載入 user。
     *
     * @return array<string, scalar|null>
     */
    public static function values(AuditLog $log): array
    {
        return [
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'user' => $log->user?->name,
            'action' => AuditLogger::label('audit.actions.' . $log->action, $log->action),
            'type' => $log->loggable_type ? AuditLogger::label('audit.types.' . class_basename($log->loggable_type), class_basename($log->loggable_type)) : null,
            'loggable_id' => $log->loggable_id,
            'description' => $log->description,
        ];
    }
}
