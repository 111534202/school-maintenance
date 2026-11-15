<?php

namespace App\Http\Controllers;

use App\Exports\SectionsExport;
use App\Models\ImportBatch;
use App\Services\AuditLogger;
use App\Services\Import\BulkImporter;
use App\Services\Import\DeviceImporter;
use App\Services\Import\UserImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 批次匯入（設備、用戶）：下載範本 → 上傳 Excel／CSV → 預覽（標出每一列對不對）→ 確認寫入。
 *
 * - 權限跟著對應的主檔：匯入設備要 devices.manage、匯入用戶要 users.manage。
 * - 上傳的檔案只暫存在私有資料夾（storage/app/private/imports），預覽後 1 小時內可確認，確認或過期後就刪掉。
 * - 確認寫入時會把檔案重新讀一次、重新檢查（避免預覽到確認之間有人新增了同編號的資料），錯誤的列一律略過。
 * 想新增一種匯入：寫一個繼承 BulkImporter 的類別，登錄在 importer()，再加對應的入口按鈕。
 */
class BulkImportController extends Controller
{
    private const TTL_MINUTES = 60;

    /** 上傳檔案大小上限（KB）。 */
    private const MAX_FILE_KB = 5120;

    /** 匯入頁（GET /import/{type}）：說明、欄位一覽、下載範本、選檔。 */
    public function index(string $type)
    {
        $importer = $this->importer($type);

        // 最近 10 次匯入，可以在畫面上整批復原。
        $batches = ImportBatch::with('creator')->where('import_type', $type)->latest('id')->limit(10)->get();

        return view('imports.index', ['importer' => $importer, 'maxRows' => $importer::MAX_ROWS, 'batches' => $batches]);
    }

    /** 下載範本（GET /import/{type}/template）：標題列＋範例＋填寫說明＋可填值一覽。 */
    public function template(string $type)
    {
        $importer = $this->importer($type);

        return Excel::download(new SectionsExport($importer->templateSheets()), $importer->label() . '匯入範本.xlsx');
    }

    /** 上傳並預覽（POST /import/{type}/preview）：還沒寫進資料庫，只是逐列檢查。 */
    public function preview(Request $request, string $type)
    {
        $importer = $this->importer($type);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:' . self::MAX_FILE_KB],
        ], [
            'file.required' => '請選擇要匯入的檔案。',
            'file.mimes' => '只能匯入 .xlsx 或 .csv 檔。',
            'file.max' => '檔案太大（上限 5 MB）。',
        ]);

        $file = $request->file('file');
        [$rows, $fatal] = $importer->parse($file);
        if ($fatal) {
            return back()->with('error', $fatal);
        }

        $results = $importer->check($rows);

        // 暫存檔案（私有、隨機檔名），確認時再用它。
        $extension = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        if (strtolower($file->getClientOriginalExtension()) === 'txt') {
            $extension = 'csv';
        }
        $token = (string) Str::uuid();
        $path = $file->storeAs('imports', "{$token}.{$extension}", 'local');
        Cache::put($this->cacheKey($request, $token), ['type' => $type, 'path' => $path, 'name' => $file->getClientOriginalName()], now()->addMinutes(self::TTL_MINUTES));

        $okCount = count(array_filter($results, fn ($r) => ! $r['errors']));

        return view('imports.preview', [
            'importer' => $importer,
            'results' => $results,
            'okCount' => $okCount,
            'errorCount' => count($results) - $okCount,
            'token' => $token,
            'fileName' => $file->getClientOriginalName(),
        ]);
    }

    /** 確認寫入（POST /import/{type}/confirm）。 */
    public function confirm(Request $request, string $type): RedirectResponse
    {
        $importer = $this->importer($type);
        $token = (string) $request->input('token');
        $stored = Cache::get($this->cacheKey($request, $token));

        if (! $stored || $stored['type'] !== $type || ! Storage::disk('local')->exists($stored['path'])) {
            return redirect()->route('imports.index', $type)->with('error', '預覽已經過期（超過 ' . self::TTL_MINUTES . ' 分鐘），請重新上傳檔案。');
        }

        set_time_limit(300);   // 用戶匯入每個密碼都要雜湊，人數多時比較久

        [$rows, $fatal] = $importer->parse($stored['path'], 'local');
        if ($fatal) {
            return redirect()->route('imports.index', $type)->with('error', $fatal);
        }
        $results = $importer->check($rows);   // 重新檢查：預覽之後資料庫可能又變了
        $okCount = count(array_filter($results, fn ($r) => ! $r['errors']));
        $skipped = count($results) - $okCount;

        if ($okCount === 0) {
            return redirect()->route('imports.index', $type)->with('error', '沒有可以匯入的資料（每一列都有錯誤）。請修正檔案後重新上傳。');
        }

        $batch = $importer->commit($results, $stored['name'], $request->user()->id);
        $count = $batch->imported_count;

        Storage::disk('local')->delete($stored['path']);
        Cache::forget($this->cacheKey($request, $token));
        AuditLogger::log('created', null, ['import' => $type, 'imported' => $count, 'skipped' => $skipped, 'file' => $stored['name']],
            "批次匯入{$importer->label()}：成功 {$count} 筆" . ($skipped ? "、略過 {$skipped} 列" : '') . "（檔案：{$stored['name']}）");

        $message = "已匯入 {$count} 筆{$importer->label()}" . ($skipped ? "，另有 {$skipped} 列有錯誤沒有匯入" : '') . '。';

        return redirect()->route($type === 'users' ? 'users.index' : 'devices.index')->with('success', $message);
    }

    /** 整批復原（POST /import/{type}/batches/{batch}/undo）：把這一批匯入的資料收回（軟刪除）；已有使用紀錄的會保留。 */
    public function undo(string $type, ImportBatch $batch): RedirectResponse
    {
        $importer = $this->importer($type);
        if ($batch->import_type !== $type) {
            abort(404);
        }
        if ($batch->isUndone()) {
            return redirect()->route('imports.index', $type)->with('error', '這一批已經復原過了。');
        }

        $result = DB::transaction(function () use ($importer, $batch) {
            $result = $importer->undo($batch);
            $batch->update(['undone_at' => now(), 'undone_by' => auth()->id()]);

            return $result;
        });
        AuditLogger::log('deleted', null, ['import' => $type, 'batch' => $batch->id, 'undone' => $result['done'], 'kept' => $result['kept']],
            "復原批次匯入{$importer->label()}（檔案：{$batch->name}）：收回 {$result['done']} 筆" . ($result['kept'] ? "、{$result['kept']} 筆已有使用紀錄而保留" : ''));

        $message = "已復原這一批匯入：收回 {$result['done']} 筆{$importer->label()}" . ($result['kept'] ? "，另有 {$result['kept']} 筆已有使用紀錄所以保留" : '') . '。';

        return redirect()->route('imports.index', $type)->with('success', $message);
    }

    /** 依網址上的種類找匯入器，並確認登入者有對應權限；種類不存在就 404。 */
    private function importer(string $type): BulkImporter
    {
        $importer = match ($type) {
            'devices' => new DeviceImporter,
            'users' => new UserImporter,
            default => abort(404),
        };
        Gate::authorize($importer->permission());

        return $importer;
    }

    /** 預覽暫存的識別：綁定登入者，別人拿到 token 也不能確認。 */
    private function cacheKey(Request $request, string $token): string
    {
        return 'bulk-import:' . $request->user()->id . ':' . preg_replace('/[^a-f0-9-]/', '', $token);
    }
}
