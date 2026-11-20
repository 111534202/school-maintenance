<?php

namespace App\Support;

use Collator;
use Illuminate\Support\Collection;

/**
 * 依中文筆畫排序（設備類別名稱用）：筆畫少的在前，同筆畫依部首；英文、數字排在中文後面。
 * 用 PHP 的 intl 擴充（Collator，台灣繁體中文的筆畫排序規則）；伺服器沒開 intl 時退回依名稱排序（和原本一樣），不會出錯。
 * 資料庫沒有內建「筆畫排序」，所以是撈出來後在 PHP 排（類別數量不多，不影響效能）。
 */
class StrokeOrder
{
    /**
     * @template T
     * @param  Collection<int, T>  $items
     * @param  callable(T): string|string  $key  取出要比較的文字（欄位名稱或函式），預設 name
     * @return Collection<int, T>
     */
    public static function sort(Collection $items, callable|string $key = 'name'): Collection
    {
        $text = is_callable($key) ? $key : fn ($item) => (string) data_get($item, $key);

        if (! class_exists(Collator::class)) {
            return $items->sortBy($text, SORT_NATURAL)->values();
        }

        $collator = new Collator('zh_Hant_TW@collation=stroke');

        return $items->sort(fn ($a, $b) => $collator->compare($text($a), $text($b)))->values();
    }
}
