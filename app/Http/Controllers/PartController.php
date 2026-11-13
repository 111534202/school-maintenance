<?php

namespace App\Http\Controllers;

use App\Exports\LowStockPartsExport;
use App\Exports\PartsExport;
use App\Exports\SectionsExport;
use App\Support\ExportSections;
use App\Support\PartHistorySections;
use App\Models\Part;
use App\Support\PartExportColumns;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 零件庫存：列表（零件庫存列表）、零件履歷、新增／修改／刪除零件。
 * 全部需要 parts.manage 權限（見 routes/web.php）。
 * 庫存數量不在這裡改：進貨用「採購紀錄」（PartPurchaseController），用料由維修與保養扣除。
 */
class PartController extends Controller
{
    /** 零件庫存列表：可依零件編號、名稱、規格、品牌、型號搜尋。 */
    public function index(Request $request)
    {
        $parts = $this->searchQuery($request)
            ->paginate(15)
            ->withQueryString();

        // 「查看目前低庫存品項」視窗用：全部低庫存的零件（不受搜尋與分頁影響）。
        $lowStockParts = $this->lowStockParts();

        // 「匯出 Excel／PDF」預覽視窗用：符合目前搜尋的全部零件（不分頁）。
        $exportParts = $this->searchQuery($request)->get();

        return view('parts.index', compact('parts', 'lowStockParts', 'exportParts'));
    }

    /**
     * 匯出零件清單（Excel 或 PDF）：只匯出預覽視窗裡勾選的零件（ids[]）與欄位（columns[]），
     * 零件範圍不超出目前的搜尋條件（keyword）。
     */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
        ], [
            'ids.required' => '請至少勾選一個零件。',
            'columns.required' => '請至少勾選一個欄位。',
        ]);

        $parts = $this->searchQuery($request)->whereIn('id', $request->input('ids'))->get();
        $columns = PartExportColumns::pick($request->input('columns'));
        $fileName = 'parts-' . now()->format('Y-m-d-His');

        if ($format === 'excel') {
            return Excel::download(new PartsExport($parts, $columns), $fileName . '.xlsx');
        }

        // 中文字型 Noto Sans TC 讀取時需要較多記憶體（同成本分析報表 PDF）。
        ini_set('memory_limit', '512M');
        File::ensureDirectoryExists(storage_path('fonts'));

        return Pdf::loadView('parts.export-pdf', [
            'rows' => $parts->map(fn (Part $part) => PartExportColumns::values($part)),
            'columns' => $columns,
            'keyword' => $request->input('keyword'),
        ])->setPaper('a4', 'landscape')->download($fileName . '.pdf');
    }

    /** 列表與匯出共用的查詢：關鍵字搜尋零件編號、名稱、規格、品牌、型號，依新增順序排列。 */
    private function searchQuery(Request $request)
    {
        return Part::query()
            ->withStockValue()
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = '%' . $request->string('keyword') . '%';
                $query->where(function ($q) use ($keyword) {
                    $q->where('part_code', 'like', $keyword)
                        ->orWhere('name', 'like', $keyword)
                        ->orWhere('specification', 'like', $keyword)
                        ->orWhere('brand', 'like', $keyword)
                        ->orWhere('model', 'like', $keyword);
                });
            })
            ->orderBy('id');   // 依新增順序（也就是零件編號順序）
    }

    /** 匯出目前低庫存品項的 Excel（內容與低庫存視窗相同）。 */
    public function exportLowStock()
    {
        return Excel::download(
            new LowStockPartsExport($this->lowStockParts()),
            'low-stock-parts-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    /** 目前庫存低於安全庫存的零件，依零件新增順序排列。 */
    private function lowStockParts()
    {
        return Part::query()
            ->whereColumn('current_stock', '<', 'safety_stock')
            ->orderBy('id')
            ->get();
    }

    public function create()
    {
        return view('parts.create', ['nextCode' => Part::nextCode()]);
    }

    /** 新增零件：編號可以自己填，沒填就自動產生；有填期初庫存時，會建立一筆「期初庫存」採購批次。 */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_code' => $this->codeRules(null, required: false),
            ...$this->basicRules(),
            'current_stock' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required_unless:current_stock,0', 'nullable', 'integer', 'min:0'],
        ], [
            'unit_price.required_unless' => '有期初庫存時，請填寫期初單價。',
            ...$this->codeMessages(),
        ]);

        $part = DB::transaction(fn () => Part::create($validated));

        AuditLogger::log('created', $part, ['part_code' => $part->part_code, 'name' => $part->name, 'current_stock' => $part->current_stock]);

        return redirect()->route('parts.show', $part)
            ->with('success', "零件新增成功，編號為 {$part->part_code}。");
    }

    /** 零件履歷：基本資料、採購紀錄、使用紀錄（維修與保養）。 */
    /**
     * 零件庫存列表／低庫存視窗點一筆時，在畫面中的對話框（標題「零件庫存」）顯示的基本資料；
     * 按「查看詳細」才進入零件履歷。版面與空間申請的「預約詳細」相同。
     */
    public function summary(Part $part)
    {
        return view('parts.summary', ['part' => $part, 'values' => PartExportColumns::values($part)]);
    }

    public function show(Part $part)
    {
        [$purchases, $usages, $scraps] = $this->history($part);

        // 「匯出 Excel／PDF」預覽視窗：基本資料、使用紀錄、採購紀錄、報廢紀錄（跟匯出的內容相同）。
        $exportSections = PartHistorySections::build($part, $usages, $purchases, $scraps);

        return view('parts.show', compact('part', 'purchases', 'usages', 'scraps', 'exportSections'));
    }

    /**
     * 匯出零件履歷（Excel 或 PDF）：只匯出預覽視窗勾選的區塊（sections[]）與欄位（cols[區塊][]）。
     */
    public function exportHistory(Request $request, Part $part, string $format)
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string', 'in:basic,usages,purchases,scraps'],
            'cols' => ['nullable', 'array'],
        ], [
            'sections.required' => '請至少勾選一個要匯出的區塊。',
        ]);

        [$purchases, $usages, $scraps] = $this->history($part);
        $sections = ExportSections::pick(
            PartHistorySections::build($part, $usages, $purchases, $scraps),
            $request->input('sections', []),
            $request->input('cols', [])
        );
        if (! $sections) {
            throw ValidationException::withMessages(['cols' => '請至少勾選一個要匯出的欄位。']);
        }

        $fileName = 'part-history-' . $part->part_code . '-' . now()->format('Y-m-d-His');
        if ($format === 'excel') {
            return Excel::download(new SectionsExport($sections), $fileName . '.xlsx');
        }

        ini_set('memory_limit', '512M');   // 中文字型需要較多記憶體（同成本分析報表 PDF）
        File::ensureDirectoryExists(storage_path('fonts'));

        return Pdf::loadView('exports.sections-pdf', [
            'title' => '零件履歷：' . $part->displayName(),
            'meta' => ['匯出時間：' . now()->format('Y-m-d H:i'), '目前庫存：' . $part->withUnit($part->current_stock) . '　安全庫存：' . $part->withUnit($part->safety_stock)],
            'sections' => $sections,
        ])->setPaper('a4', 'landscape')->download($fileName . '.pdf');
    }

    /**
     * 零件履歷的三種紀錄（頁面與匯出共用）：採購紀錄、使用紀錄（維修＋保養，新的在上面）、報廢紀錄。
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function history(Part $part): array
    {
        $part->loadMissing('purchases.attachments');
        $purchases = $part->purchases->sortByDesc(fn ($p) => $p->purchase_date->format('Ymd') . sprintf('%010d', $p->id))->values();   // 新的在上面

        $repairUsages = $part->repairPartUsages()
            ->with(['repairLog.repairRequest.assignedTechnician', 'purchase'])
            ->get()
            ->map(fn ($usage) => [
                'source' => '報修',
                'reference' => $usage->repairLog?->repairRequest,
                'link' => $usage->repairLog?->repair_request_id ? route('repairs.show', $usage->repairLog->repair_request_id) : null,
                'reference_label' => $usage->repairLog?->repair_request_id ? '報修單 #' . $usage->repairLog->repair_request_id : '—',
                'status' => $usage->repairLog?->repairRequest?->status?->label(),
                'used_at' => $usage->repairLog?->started_at ?? $usage->created_at,
                'executor' => $usage->repairLog?->repairRequest?->assignedTechnician?->name,
                'quantity' => $usage->quantity,
                'unit_price' => $usage->unit_price,
                'batch' => $usage->purchase,
            ]);

        $maintenanceUsages = $part->maintenancePartUsages()
            ->with(['maintenanceResult.maintenanceOrder', 'maintenanceResult.executor', 'purchase'])
            ->get()
            ->map(fn ($usage) => [
                'source' => '保養',
                'link' => $usage->maintenanceResult ? route('maintenance-orders.show', $usage->maintenanceResult->maintenance_order_id) : null,
                'reference_label' => $usage->maintenanceResult ? '保養工單 #' . $usage->maintenanceResult->maintenance_order_id : '—',
                'status' => $usage->maintenanceResult?->maintenanceOrder?->statusLabel()[0] ?? null,
                'used_at' => $usage->maintenanceResult?->executed_at ?? $usage->created_at,
                'executor' => $usage->maintenanceResult?->executor?->name,
                'quantity' => $usage->quantity,
                'unit_price' => $usage->unit_price,
                'batch' => $usage->purchase,
            ]);

        // 使用紀錄：使用日期時間由遠到近；同一個時間（例如同一張報修單一次扣了兩個批次）再依扣用批次的採購日期（舊的在前）。
        $usages = $repairUsages->concat($maintenanceUsages)
            ->sortBy(fn (array $usage) => sprintf(
                '%s|%s|%010d',
                $usage['used_at']?->format('Y-m-d H:i:s') ?? '',
                $usage['batch']?->purchase_date?->format('Y-m-d') ?? '0000-00-00',
                $usage['batch']?->id ?? 0
            ))
            ->values();

        // 報廢紀錄：新的在上面；batches 用來算報廢損失。
        $scraps = $part->scraps()->with(['batches.purchase', 'recorder'])->orderByDesc('scrap_date')->orderByDesc('id')->get();

        return [$purchases, $usages, $scraps];
    }

    public function edit(Part $part)
    {
        return view('parts.edit', compact('part'));
    }

    /** 修改基本資料（零件編號、名稱、規格、品牌、型號、安全庫存）；目前庫存不能在這裡改。 */
    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'part_code' => $this->codeRules($part->id, required: true),
            ...$this->basicRules(),
        ], $this->codeMessages());

        $part->update($validated);

        AuditLogger::log('updated', $part, $part->getChanges());

        return redirect()->route('parts.show', $part)
            ->with('success', '零件基本資料已更新。');
    }

    /** 刪除零件：已經被維修、保養用過或有報廢紀錄的零件不能刪除（紀錄要留著）。 */
    public function destroy(Part $part)
    {
        if ($part->repairPartUsages()->exists() || $part->maintenancePartUsages()->exists() || $part->scraps()->exists()) {
            return redirect()->route('parts.show', $part)
                ->with('error', "零件 {$part->part_code} 已經有維修、保養使用或報廢紀錄，不能刪除。");
        }

        DB::transaction(function () use ($part) {
            foreach ($part->purchases as $purchase) {
                $purchase->deleteAttachments();
            }
            $part->delete();   // 採購紀錄會跟著刪除（外鍵 cascade）
        });

        AuditLogger::log('deleted', $part, ['part_code' => $part->part_code, 'name' => $part->name]);

        return redirect()->route('parts.index')
            ->with('success', "零件 {$part->part_code} 已刪除。");
    }

    /**
     * 即時檢查零件編號能不能用（新增、修改表單輸入時呼叫，回傳 JSON）。
     * 這只是提早提醒；真正送出時伺服器還會再檢查一次，避免兩個人同時送出相同編號。
     */
    public function checkCode(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $ignoreId = $request->integer('ignore') ?: null;

        if ($code === '') {
            return response()->json(['available' => true, 'message' => '未填寫，儲存時會自動產生 ' . Part::nextCode() . '。']);
        }

        if (! preg_match(self::CODE_PATTERN, $code) || mb_strlen($code) > 20) {
            return response()->json(['available' => false, 'message' => '零件編號只能用英文字母、數字、「-」、「_」、「.」，最多 20 個字。']);
        }

        $conflict = Part::codeConflict($code, $ignoreId);

        return $conflict
            ? response()->json(['available' => false, 'message' => "{$conflict}，請換一個。"])
            : response()->json(['available' => true, 'message' => "零件編號「{$code}」可以使用。"]);
    }

    /** 零件編號允許的字元：英文字母、數字、- _ .（會印在標籤或表單上，所以不允許空白與特殊符號）。 */
    private const CODE_PATTERN = '/^[A-Za-z0-9._-]+$/';

    /** 零件編號用在網址裡，這些和固定網址重複（或瀏覽器會改寫的「.」「..」）不能當編號。 */
    private const RESERVED_CODES = ['create', 'check-code', 'low-stock', 'export', '.', '..'];

    /** 零件編號的驗證規則：格式、長度，以及不可和其他零件編號、設備編號重複（大小寫視為相同）。 */
    private function codeRules(?int $ignoreId, bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            'regex:' . self::CODE_PATTERN,
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
                // 零件編號會出現在網址（/parts/編號），不能和固定的網址名稱一樣。
                if (filled($value) && in_array(strtolower($value), self::RESERVED_CODES, true)) {
                    $fail('這個零件編號是系統保留的名稱，請換一個。');
                } elseif (filled($value) && ($conflict = Part::codeConflict($value, $ignoreId))) {
                    $fail("{$conflict}，請換一個。");
                }
            },
        ];
    }

    private function codeMessages(): array
    {
        return [
            'part_code.required' => '請填寫零件編號。',
            'part_code.max' => '零件編號最多 20 個字。',
            'part_code.regex' => '零件編號只能用英文字母、數字、「-」、「_」、「.」。',
        ];
    }

    /** 新增與修改共用的基本資料驗證。 */
    private function basicRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'specification' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:10'],
            'safety_stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
