<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceResultRequest;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\Part;
use App\Services\NgToRepairService;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceResultController extends Controller
{
    /**
     * 保養結果回報表單（第 2 週任務）。
     */
    public function create(MaintenanceOrder $maintenanceOrder): View
    {
        abort_if($maintenanceOrder->result()->exists(), 422, '這張工單已經回報過保養結果了');

        // 保養用料下拉選單：只列出還有庫存的零件。
        $parts = Part::query()->where('current_stock', '>', 0)->orderBy('name')->get();

        return view('maintenance_results.create', ['order' => $maintenanceOrder, 'parts' => $parts]);
    }

    /**
     * 保養結果詳細頁（第 3 週任務 3）：原本結果只以卡片形式嵌在工單詳細頁裡，
     * 這裡補一個獨立的詳細頁，之後附件共用機制正式納入時，就在這個頁面上顯示附件，
     * 不用再改工單詳細頁。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        abort_unless($maintenanceOrder->result()->exists(), 404);

        $maintenanceOrder->load(['result.partUsages.part', 'maintenancePlan']);

        return view('maintenance_results.show', ['order' => $maintenanceOrder, 'result' => $maintenanceOrder->result]);
    }

    /**
     * 儲存保養結果，並將工單標記為已完成；若結果為 NG，交給 NgToRepairService 轉報修。
     */
    public function store(StoreMaintenanceResultRequest $request, MaintenanceOrder $maintenanceOrder, NgToRepairService $ngToRepairService, PartStockService $stock): RedirectResponse
    {
        abort_if($maintenanceOrder->result()->exists(), 422, '這張工單已經回報過保養結果了');

        $validated = $request->validated();
        $partUsages = $validated['part_usages'] ?? [];
        unset($validated['part_usages']);

        // 保養結果、用料扣庫存、工單完成放在同一個交易：任何一個零件庫存不足，整筆都不會寫入。
        $result = DB::transaction(function () use ($validated, $partUsages, $request, $maintenanceOrder, $stock) {
            $result = MaintenanceResult::create([
                ...$validated,
                'executed_by' => $request->user()->id,   // 執行人 = 登入帳號（外鍵 users.id），不能自己亂填
                'maintenance_order_id' => $maintenanceOrder->id,
            ]);

            foreach ($partUsages as $index => $usage) {
                if (empty($usage['part_id'])) {
                    continue;   // 空白列代表沒有選零件
                }

                $part = Part::query()->findOrFail((int) $usage['part_id']);

                // 依先進先出扣庫存；跨批次時一批建立一列用料明細。
                try {
                    $allocations = $stock->consume($part, (int) $usage['quantity']);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(["part_usages.{$index}.quantity" => $exception->getMessage()]);
                }

                foreach ($allocations as $allocation) {
                    $result->partUsages()->create(['part_id' => $part->id, ...$allocation]);
                }
            }

            $maintenanceOrder->update(['status' => MaintenanceOrder::STATUS_COMPLETED]);

            return $result;
        });

        $ngToRepairService->convert($result);

        $result->refresh();

        if (! $result->isNg()) {
            $status = '保養結果已回報為 OK，工單已完成';
        } elseif ($result->ng_conversion_status === MaintenanceResult::NG_CONVERSION_CONVERTED) {
            $status = '保養結果已回報為 NG，已自動建立報修單（編號 #'.$result->repair_request_id.'），請至報修看板追蹤';
        } else {
            $status = '保養結果已回報為 NG，已標記待轉報修（報修模組併入後會自動建立報修單）';
        }

        return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('success', $status);
    }
}
