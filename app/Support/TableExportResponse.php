<?php

namespace App\Support;

use App\Exports\TableExport;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 列表頁「匯出 Excel」的共用回應：
 * 先預覽（網址加上 preview=1，回傳表格資料給預覽視窗），確認後才真的下載 Excel 並寫入操作紀錄。
 * 預覽不寫操作紀錄，因為還沒有真的匯出。
 */
class TableExportResponse
{
    /** 預覽視窗最多顯示幾列（完整資料仍會在下載的 Excel 裡）。 */
    public const PREVIEW_ROWS = 100;

    /**
     * @param  string  $sheetTitle  工作表名稱
     * @param  list<string>  $headings  欄位標題
     * @param  list<list<scalar|null>>  $rows  資料列
     * @param  string  $file  下載檔名（含副檔名）
     * @param  string  $logLabel  操作紀錄的名稱，例如「設備清單」
     */
    public static function respond(Request $request, string $sheetTitle, array $headings, array $rows, string $file, string $logLabel): JsonResponse|BinaryFileResponse
    {
        if ($request->boolean('preview')) {
            return response()->json([
                'file' => $file,
                'headings' => $headings,
                'rows' => array_slice($rows, 0, self::PREVIEW_ROWS),
                'total' => count($rows),
                'limit' => self::PREVIEW_ROWS,
            ]);
        }

        AuditLogger::log('downloaded', null, ['file' => $file, 'rows' => count($rows)], "匯出{$logLabel} {$file}（" . count($rows) . ' 筆）');

        return Excel::download(new TableExport($sheetTitle, $headings, $rows), $file);
    }
}
