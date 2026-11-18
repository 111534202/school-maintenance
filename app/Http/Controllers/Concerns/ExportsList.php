<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\TableExport;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 列表「匯出 Excel」共用的流程（設備、用戶、報修單、空間申請）：
 * 預覽視窗送來 columns[]（勾選的欄位）與 ids[]（勾選的資料）；
 * 沒帶 columns 就是全部欄位、沒帶 ids 就是目前搜尋與篩選條件下的全部
 * （預覽只列前 500 筆，資料超過時全選會不帶 ids，匯出符合條件的全部，見 layouts/partials/export-preview）。
 * ids 只會在原本的查詢裡再縮小範圍，不會超出搜尋條件與權限。每次匯出都寫一筆操作紀錄。
 */
trait ExportsList
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query  已套好權限、搜尋與篩選的查詢
     * @param  class-string  $columnsClass  欄位定義（要有 pick() 與 values()，例如 DeviceExportColumns）
     * @param  string  $title  操作紀錄裡的名稱，例如「設備清單」
     * @param  string  $filePrefix  檔名開頭，例如「設備清單-」
     */
    protected function exportList(Request $request, Builder $query, string $columnsClass, string $title, string $filePrefix, string $sheet, ?int $limit = null)
    {
        $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        if ($request->filled('ids')) {
            $query->whereIn($query->getModel()->getQualifiedKeyName(), $request->input('ids'));
        }
        if ($limit) {
            $query->limit($limit);
        }

        $columns = $columnsClass::pick($request->input('columns'));
        $keys = array_keys($columns);
        $records = $query->get()->map(fn ($model) => $columnsClass::values($model));
        $file = $filePrefix . now()->format('Ymd-His') . '.xlsx';

        AuditLogger::log('downloaded', null, ['file' => $file, 'rows' => $records->count(), 'columns' => $keys], "匯出{$title} {$file}（" . $records->count() . ' 筆）');

        $rows = $records->map(fn (array $values) => array_map(fn (string $key) => $values[$key], $keys))->all();

        return Excel::download(new TableExport($sheet, array_values($columns), $rows), $file);
    }
}
