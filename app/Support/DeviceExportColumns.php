<?php

namespace App\Support;

use App\Models\Device;

/**
 * 設備清單匯出的欄位：欄位代號 => 欄位名稱（依顯示順序）。
 * 匯出前的預覽視窗列出這些欄位讓使用者勾選，預覽與 Excel 用同一份資料（values）。
 */
class DeviceExportColumns
{
    public const COLUMNS = [
        'device_code' => '設備編號',
        'asset_code' => '資產編號',
        'category' => '類別',
        'brand' => '品牌',
        'model' => '型號',
        'serial_number' => '序號',
        'classroom_code' => '教室代碼',
        'classroom' => '教室',
        'status' => '狀態',
        'warranty_end_date' => '保固到期日',
        'created_at' => '建立時間',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = [];

    /**
     * 依勾選的欄位代號取出欄位；沒勾任何欄位（或代號都不存在）就給全部。
     *
     * @param  list<string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    /** 預覽每一列左邊勾選框的說明文字。 */
    public static function label(Device $device): string
    {
        return (string) $device->device_code;
    }

    /**
     * 一台設備的每個欄位值。呼叫前要先載入 category、classroom。
     *
     * @return array<string, scalar|null>
     */
    public static function values(Device $device): array
    {
        return [
            'device_code' => $device->device_code,
            'asset_code' => $device->asset_code,
            'category' => $device->category?->name,
            'brand' => $device->brand,
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'classroom_code' => $device->classroom?->classroom_code,
            'classroom' => $device->classroom?->name,
            'status' => Device::statusLabel($device->display_status),
            'warranty_end_date' => $device->warranty_end_date?->format('Y-m-d'),
            'created_at' => $device->created_at?->format('Y-m-d H:i'),
        ];
    }
}
