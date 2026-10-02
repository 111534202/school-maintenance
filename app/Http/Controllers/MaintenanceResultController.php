<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceResultRequest;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Services\NgToRepairService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MaintenanceResultController extends Controller
{
    /**
     * 保養結果回報表單（第 2 週任務）。
     */
    public function create(MaintenanceOrder $maintenanceOrder): View
    {
        abort_if($maintenanceOrder->result()->exists(), 422, '這張工單已經回報過保養結果了');

        return view('maintenance_results.create', ['order' => $maintenanceOrder]);
    }

    /**
     * 保養結果詳細頁（第 3 週任務 3）：原本結果只以卡片形式嵌在工單詳細頁裡，
     * 這裡補一個獨立的詳細頁，之後附件共用機制正式納入時，就在這個頁面上顯示附件，
     * 不用再改工單詳細頁。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        abort_unless($maintenanceOrder->result()->exists(), 404);

        $maintenanceOrder->load(['result', 'maintenancePlan']);

        return view('maintenance_results.show', ['order' => $maintenanceOrder, 'result' => $maintenanceOrder->result]);
    }

    /**
     * 儲存保養結果，並將工單標記為已完成；若結果為 NG，交給 NgToRepairService 轉報修。
     */
    public function store(StoreMaintenanceResultRequest $request, MaintenanceOrder $maintenanceOrder, NgToRepairService $ngToRepairService): RedirectResponse
    {
        abort_if($maintenanceOrder->result()->exists(), 422, '這張工單已經回報過保養結果了');

        $result = MaintenanceResult::create([
            ...$request->validated(),
            'maintenance_order_id' => $maintenanceOrder->id,
        ]);

        $maintenanceOrder->update(['status' => MaintenanceOrder::STATUS_COMPLETED]);

        $ngToRepairService->convert($result);

        $status = $result->isNg()
            ? '保養結果已回報為 NG，已標記待轉報修（等彭仕衡的報修介面串接後會自動建立報修單）'
            : '保養結果已回報為 OK，工單已完成';

        return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('success', $status);
    }
}
