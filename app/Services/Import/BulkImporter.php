<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * 批次匯入（Excel／CSV）的共用流程，設備匯入與用戶匯入各自繼承它，只需要說明「有哪些欄位、每列怎麼檢查、怎麼寫入」：
 *
 * 1. parse()：讀檔，把每一列依標題轉成「欄位代碼 => 值」。標題可以寫中文名稱（可帶 *）或欄位代碼，順序不拘。
 * 2. preview()：逐列檢查（必填、格式、對照主檔、資料庫重複、檔案內重複），回傳每一列的結果；錯誤的列不會寫入。
 * 3. commit()：確認後把「沒有錯誤的列」寫進資料庫（同一個資料庫交易，中途出錯整批不寫入）。
 *
 * 想新增一種匯入：繼承本類別 → 實作 columns()／checkRow()／persist() → 在 BulkImportController::importer() 登錄。
 */
abstract class BulkImporter
{
    /** 一次最多匯入幾列（超過請分檔）。子類別可以調整。 */
    public const MAX_ROWS = 500;

    /** 種類代碼，用在網址（/import/{type}）。 */
    abstract public function type(): string;

    /** 畫面標題，例如「設備」。 */
    abstract public function label(): string;

    /** 需要的權限代碼。 */
    abstract public function permission(): string;

    /**
     * 欄位定義：每個欄位 ['key' => 欄位代碼, 'label' => 中文標題, 'required' => 是否必填, 'example' => 範例值, 'note' => 填寫說明]。
     *
     * @return list<array{key: string, label: string, required: bool, example: string, note: string}>
     */
    abstract public function columns(): array;

    /**
     * 檢查一列。回傳 [錯誤訊息清單, 整理後可寫入的資料]；有錯誤時第二個可以是空陣列。
     * $seen 是同一個檔案裡前面幾列已經用過的值（檢查檔案內重複用），子類別自己維護。
     *
     * @param  array<string, string>  $row  欄位代碼 => 文字值（已去除前後空白）
     * @param  array<string, mixed>  $seen
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    abstract protected function checkRow(array $row, array &$seen): array;

    /** 把整理好的一列寫進資料庫（已在交易裡）；要用 $this->batchId 標記這筆資料是哪一次匯入來的。 */
    abstract protected function persist(array $data): void;

    /**
     * 整批復原：把這一批匯入的資料收回（軟刪除，資料不會真的消失）。已經有使用紀錄的資料會保留。
     *
     * @return array{done: int, kept: int} 復原幾筆、因為已有使用紀錄而保留幾筆
     */
    abstract public function undo(ImportBatch $batch): array;

    /** 目前這次匯入的批次編號（commit 時設定，persist 用來標記資料）。 */
    protected int $batchId = 0;

    /** 寫入完成後的收尾（例如重新計算教室的異常標記），預設不做事。 */
    protected function afterCommit(array $rows): void {}

    /**
     * 給使用者參考的對照表（例如「有哪些類別、教室代碼可以填」），放在範本的第二張工作表。
     *
     * @return list<array{title: string, headings: list<string>, rows: list<list<string>>}>
     */
    public function referenceTables(): array
    {
        return [];
    }

    /** 只填標題列的範本，搭配「填寫說明」「範例」工作表。 */
    public function templateSheets(): array
    {
        $columns = $this->columns();
        $headings = array_map(fn ($c) => $c['label'] . ($c['required'] ? '*' : ''), $columns);

        $sheets = [
            ['title' => '匯入資料', 'headings' => $headings, 'rows' => []],
            ['title' => '範例', 'headings' => $headings, 'rows' => [array_column($columns, 'example')]],
            ['title' => '填寫說明', 'headings' => ['欄位', '必填', '說明'], 'rows' => array_map(
                fn ($c) => [$c['label'], $c['required'] ? '是' : '否', $c['note']],
                $columns
            )],
        ];
        foreach ($this->referenceTables() as $table) {
            $sheets[] = $table;
        }

        return $sheets;
    }

    /**
     * 讀檔並轉成「列」。回傳 [列清單, 致命錯誤]；致命錯誤（讀不出來、缺必要欄位、超過上限）時列清單是空的。
     *
     * @return array{0: list<array{line: int, values: array<string, string>}>, 1: ?string}
     */
    public function parse(UploadedFile|string $file, ?string $disk = null): array
    {
        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $rows): void {}
            }, $file, $disk);
        } catch (\Throwable $e) {
            return [[], '檔案讀不出來，請確認是 .xlsx 或 .csv（CSV 請存成 UTF-8）。'];
        }

        $sheet = $sheets[0] ?? [];
        if (count($sheet) < 1) {
            return [[], '檔案是空的。'];
        }

        // 標題列：中文名稱（可帶 * 與空白）或欄位代碼，都對得上。
        $map = [];
        foreach ($this->columns() as $column) {
            $map[$this->normalizeHeading($column['label'])] = $column['key'];
            $map[$this->normalizeHeading($column['key'])] = $column['key'];
        }
        $index = [];   // 欄位位置 => 欄位代碼
        foreach ($sheet[0] as $position => $heading) {
            $normalized = $this->normalizeHeading((string) $heading);
            if (isset($map[$normalized])) {
                $index[$position] = $map[$normalized];
            }
        }
        $missing = [];
        foreach ($this->columns() as $column) {
            if ($column['required'] && ! in_array($column['key'], $index, true)) {
                $missing[] = $column['label'];
            }
        }
        if ($missing) {
            return [[], '找不到必要的欄位標題：' . implode('、', $missing) . '。請使用「下載範本」的標題列。'];
        }

        $rows = [];
        foreach (array_slice($sheet, 1, null, true) as $offset => $cells) {
            $values = [];
            foreach ($index as $position => $key) {
                $values[$key] = $this->cell($cells[$position] ?? null, $key);
            }
            if (implode('', $values) === '') {
                continue;   // 整列都是空的就略過
            }
            $rows[] = ['line' => $offset + 1, 'values' => $values];
        }

        if (count($rows) > static::MAX_ROWS) {
            return [[], '一次最多匯入 ' . static::MAX_ROWS . ' 列，這個檔案有 ' . count($rows) . ' 列，請分成幾個檔案。'];
        }
        if (! $rows) {
            return [[], '標題列以下沒有資料。'];
        }

        return [$rows, null];
    }

    /**
     * 逐列檢查。回傳每一列的結果：['line', 'values', 'errors', 'data']。
     *
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<array{line: int, values: array<string, string>, errors: list<string>, data: array<string, mixed>}>
     */
    public function check(array $rows): array
    {
        $seen = [];
        $results = [];
        foreach ($rows as $row) {
            $seen['__line'] = $row['line'];   // 目前是第幾列，讓「檔案內重複」的訊息能指出另一列
            [$errors, $data] = $this->checkRow($row['values'], $seen);
            $results[] = ['line' => $row['line'], 'values' => $row['values'], 'errors' => $errors, 'data' => $errors ? [] : $data];
        }

        return $results;
    }

    /**
     * 把沒有錯誤的列寫進資料庫，並留一筆批次紀錄（之後可整批復原）。回傳這一批的紀錄。
     *
     * @param  list<array{errors: list<string>, data: array<string, mixed>}>  $results
     */
    public function commit(array $results, string $fileName = '', ?int $userId = null): ImportBatch
    {
        $good = array_values(array_filter($results, fn ($r) => ! $r['errors']));

        $batch = DB::transaction(function () use ($good, $results, $fileName, $userId) {
            $batch = ImportBatch::create([
                'import_type' => $this->type(),
                'name' => mb_substr($fileName, 0, 190),
                'imported_count' => count($good),
                'skipped_count' => count($results) - count($good),
                'created_by' => $userId,
            ]);
            $this->batchId = $batch->id;
            foreach ($good as $row) {
                $this->persist($row['data']);
            }

            return $batch;
        });
        $this->afterCommit(array_column($good, 'data'));

        return $batch;
    }

    // ---- 子類別常用的小工具 ----

    /** 「是／否」類欄位：是、Y、1、true → true；否、N、0、false → false；空白 → 預設值；看不懂 → null。 */
    protected function toBool(string $value, bool $default): ?bool
    {
        if ($value === '') {
            return $default;
        }

        return match (mb_strtolower($value)) {
            '是', 'y', 'yes', '1', 'true', 'v', '○' => true,
            '否', 'n', 'no', '0', 'false', 'x' => false,
            default => null,
        };
    }

    /** 日期欄位：接受 2026-11-15、2026/11/15 或 Excel 的日期；空白 → null；看不懂 → false。 */
    protected function toDate(string $value): Carbon|false|null
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}$/', $value)) {
            try {
                return Carbon::parse(str_replace(['/', '.'], '-', $value))->startOfDay();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    private function normalizeHeading(string $heading): string
    {
        return mb_strtolower(preg_replace('/[\s*＊]+/u', '', $heading));
    }

    /** 儲存格 → 文字：數字去掉多餘的 .0、Excel 日期序號轉成日期文字，其他去除前後空白。 */
    private function cell(mixed $value, string $key): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) || is_int($value)) {
            // 日期欄位（欄位代碼以 _date 結尾）裡的數字是 Excel 的日期序號。
            if (str_ends_with($key, '_date') && $value > 20000 && $value < 90000) {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            }

            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }
}
