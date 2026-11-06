<?php

namespace App\Support;

use App\Models\Part;

/**
 * 零件庫存列表「匯出 Excel／PDF」可以選的欄位（跟零件履歷「基本資料」看到的一樣）。
 * 匯出前的預覽視窗、Excel、PDF 都用這裡的欄位名稱與顯示文字，三邊內容一致。
 */
class PartExportColumns
{
    /** 欄位代號 => 欄位名稱（依顯示順序）。 */
    public const COLUMNS = [
        'part_code' => '零件編號',
        'name' => '名稱',
        'specification' => '規格',
        'brand_model' => '品牌／型號',
        'average_price' => '平均單價',
        'current_stock' => '目前庫存',
        'safety_stock' => '安全庫存',
    ];

    /** 靠右對齊的欄位（金額與數量）。 */
    public const NUMERIC = ['average_price', 'current_stock', 'safety_stock'];

    /**
     * 只留下有效的欄位代號，並照固定順序排列；什麼都沒選時回傳全部。
     *
     * @param  array<int, string>|null  $keys
     * @return array<string, string>
     */
    public static function pick(?array $keys): array
    {
        $picked = array_intersect_key(self::COLUMNS, array_flip($keys ?? []));

        return $picked ?: self::COLUMNS;
    }

    /**
     * 一個零件在各欄位的顯示文字（例如「NT$ 10」「9 條」）。
     *
     * @return array<string, string>
     */
    public static function values(Part $part): array
    {
        $average = $part->averageUnitPrice();

        return [
            'part_code' => $part->part_code,
            'name' => $part->name,
            'specification' => $part->specification ?? '—',
            'brand_model' => $part->brandModel() ?? '—',
            'average_price' => $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.'),
            'current_stock' => $part->withUnit($part->current_stock),
            'safety_stock' => $part->withUnit($part->safety_stock),
        ];
    }
}
