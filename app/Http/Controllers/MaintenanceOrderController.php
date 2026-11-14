<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaintenanceOrderController extends Controller
{
    /**
     * 保養工單列表（第 1 週任務 6；第 2 週任務 6 加上狀態/來源/日期篩選，
     * 供設備履歷、Dashboard 等其他模組之後也能穩定查詢）。
     */
    public function index(Request $request): View
    {
        $orders = MaintenanceOrder::query()
            ->with(['maintenancePlan', 'result', 'device'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('scheduled_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('scheduled_date', '<=', $request->date('to')))
            // 排序：
            // 1. 待處理排最前面，進行中其次，已完成排最後。
            // 2. 還沒完成的（待處理、進行中）：排定日期近的在上 → 同一天建立得早的在上 → 再依來源計畫名稱 A→Z。
            // 3. 已完成的：排定日期遠的（最新的）在上 → 同一天建立得晚的在上（早的在下）→ 再依來源計畫名稱 A→Z。
            // 最後用工單編號，讓完全相同的幾筆每次順序都一樣。
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN status <> ? THEN scheduled_date END ASC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status = ? THEN scheduled_date END DESC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status <> ? THEN created_at END ASC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status = ? THEN created_at END DESC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderBy(MaintenancePlan::select('name')->whereColumn('maintenance_plans.id', 'maintenance_orders.maintenance_plan_id'))
            ->orderBy('maintenance_orders.id')
            ->paginate(15)
            ->withQueryString();

        return view('maintenance_orders.index', compact('orders'));
    }

    /**
     * 保養工單詳細頁（第 1 週任務 6；第 2 週起一併顯示保養結果）。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        $maintenanceOrder->load('maintenancePlan.maintenanceItems', 'result', 'device');

        return view('maintenance_orders.show', compact('maintenanceOrder'));
    }

    /**
     * 由保養計畫人工建立一筆保養工單（第 1 週任務 5）。
     * 本週僅做「人工由計畫建單」，不做排程自動產生；
     * 設備類別、排定保養日期直接沿用來源計畫的資料，驗證資料鏈路可以串起來即可。
     */
    public function storeFromPlan(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        abort_unless($maintenancePlan->is_active, 422, '已停用的保養計畫不能建立工單');

        MaintenanceOrder::create([
            'maintenance_plan_id' => $maintenancePlan->id,
            'device_id' => $maintenancePlan->device_id,
            'device_category_id' => $maintenancePlan->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => $maintenancePlan->next_due_date,
        ]);

        return redirect()->route('maintenance-orders.index')->with('success', '已依保養計畫「'.$maintenancePlan->name.'」建立保養工單');
    }
}
