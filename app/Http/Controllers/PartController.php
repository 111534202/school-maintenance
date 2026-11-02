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

        return view('parts.index', compact('parts'));
    }

    public function create()
    {
        return view('parts.create', ['nextCode' => Part::nextCode()]);
    }

    /** 新增零件：編號自動產生；有填期初庫存時，會建立一筆「期初庫存」採購批次。 */
    public function store(Request $request)
    {
        $validated = $request->validate([
            ...$this->basicRules(),
            'current_stock' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required_unless:current_stock,0', 'nullable', 'integer', 'min:0'],
        ], [
            'unit_price.required_unless' => '有期初庫存時，請填寫期初單價。',
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

    /** 修改基本資料（名稱、規格、品牌、型號、安全庫存）；編號與目前庫存不能在這裡改。 */
    public function update(Request $request, Part $part)
    {
        $validated = $request->validate($this->basicRules());

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
                $purchase->attachments()->delete();
            }
            $part->delete();   // 採購紀錄會跟著刪除（外鍵 cascade）
        });

        AuditLogger::log('deleted', $part, ['part_code' => $part->part_code, 'name' => $part->name]);

        return redirect()->route('parts.index')
            ->with('success', "零件 {$part->part_code} 已刪除。");
    }

    /** 新增與修改共用的基本資料驗證。 */
    private function basicRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'specification' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'safety_stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
