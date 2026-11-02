<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $parts = Part::query()
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
            ->orderBy('id')   // 依新增順序（也就是零件編號順序）
            ->paginate(20)
            ->withQueryString();

        // 「查看目前低庫存品項」視窗用：全部低庫存的零件（不受搜尋與分頁影響）。
        $lowStockParts = Part::query()
            ->whereColumn('current_stock', '<', 'safety_stock')
            ->orderBy('id')
            ->get();

        return view('parts.index', compact('parts', 'lowStockParts'));
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
    public function show(Part $part)
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

        $usages = $repairUsages->concat($maintenanceUsages)->sortByDesc('used_at')->values();

        return view('parts.show', compact('part', 'purchases', 'usages'));
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

    /** 刪除零件：已經被維修或保養用過的零件不能刪除（用料紀錄要留著）。 */
    public function destroy(Part $part)
    {
        if ($part->repairPartUsages()->exists() || $part->maintenancePartUsages()->exists()) {
            return redirect()->route('parts.show', $part)
                ->with('error', "零件 {$part->part_code} 已經有維修或保養使用紀錄，不能刪除。");
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

        return Part::codeTaken($code, $ignoreId)
            ? response()->json(['available' => false, 'message' => "零件編號「{$code}」已經被使用，請換一個。"])
            : response()->json(['available' => true, 'message' => "零件編號「{$code}」可以使用。"]);
    }

    /** 零件編號允許的字元：英文字母、數字、- _ .（會印在標籤或表單上，所以不允許空白與特殊符號）。 */
    private const CODE_PATTERN = '/^[A-Za-z0-9._-]+$/';

    /** 零件編號的驗證規則：格式、長度，以及不可和其他零件重複（大小寫視為相同）。 */
    private function codeRules(?int $ignoreId, bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            'regex:' . self::CODE_PATTERN,
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
                if (filled($value) && Part::codeTaken($value, $ignoreId)) {
                    $fail("零件編號「{$value}」已經被使用，請換一個。");
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
