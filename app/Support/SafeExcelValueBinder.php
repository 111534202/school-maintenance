<?php

namespace App\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use Override;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * 匯出 Excel 時，以 = + - @ 開頭的文字一律當「文字」寫入，不當公式。
 * 否則有人把報修標題寫成 =HYPERLINK(...)，管理員打開匯出檔時就會執行那個公式（Excel 公式注入）。
 * 一般的數字（例如 -5）不受影響。
 */
class SafeExcelValueBinder extends DefaultValueBinder
{
    #[Override]
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
