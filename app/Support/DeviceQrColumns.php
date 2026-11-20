<?php

namespace App\Support;

use App\Models\Device;

/**
 * 「列印 QR 貼紙」對話框裡預覽表格的欄位：只列辨認設備用的幾欄（貼紙本身印什麼見 devices/qr-sheet.blade.php）。
 * 跟匯出預覽共用 layouts/partials/export-preview（不顯示欄位勾選）。
 */
class DeviceQrColumns
{
    public const COLUMNS = [
        'device_code' => '設備編號',
        'category' => '類別',
        'brand_model' => '品牌／型號',
        'classroom' => '教室',
        'status' => '狀態',
    ];

    /** 靠右對齊的欄位（數字）。 */
    public const NUMERIC = [];

    /** 每一列左邊勾選框的說明文字。 */
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
            'category' => $device->category?->name,
            'brand_model' => trim(($device->brand ?? '') . ' ' . ($device->model ?? '')) ?: null,
            'classroom' => $device->classroom ? $device->classroom->classroom_code . ' ' . $device->classroom->name : null,
            'status' => Device::statusLabel($device->display_status),
        ];
    }
}
