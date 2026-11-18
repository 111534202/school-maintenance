<?php

namespace App\Support;

use App\Models\User;

/**
 * 用戶名單匯出的欄位（不含密碼）：欄位代號 => 欄位名稱（依顯示順序）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選，預覽與 Excel 用同一份資料（values）。
 */
class UserExportColumns
{
    public const COLUMNS = [
        'account' => '帳號',
        'name' => '姓名',
        'email' => 'Email',
        'phone' => '電話',
        'role' => '身分',
        'department' => '部門',
        'status' => '狀態',
        'last_login_at' => '最後登入',
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

    public static function label(User $user): string
    {
        return (string) ($user->account ?? $user->name);
    }

    /**
     * 一位用戶的每個欄位值。呼叫前要先載入 role、department。
     *
     * @return array<string, scalar|null>
     */
    public static function values(User $user): array
    {
        return [
            'account' => $user->account,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->name,
            'department' => $user->department?->name,
            'status' => $user->deleted_at ? '已刪除' : ($user->is_active ? '啟用' : '停用'),
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i'),
            'created_at' => $user->created_at?->format('Y-m-d H:i'),
        ];
    }
}
