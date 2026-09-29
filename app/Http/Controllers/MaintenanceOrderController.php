<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MaintenanceOrderController extends Controller
{
    /**
     * 保養工單列表（第 1 週任務 6）。
     */
    public function index(): View
    {
        $orders = MaintenanceOrder::with('maintenancePlan')
            ->orderByDesc('scheduled_date')
            ->paginate(15);

        return view('maintenance_orders.index', compact('orders'));
    }

    /**
     * 保養工單詳細頁（第 1 週任務 6）。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        $maintenanceOrder->load('maintenancePlan.maintenanceItems');

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
            'device_category' => $maintenancePlan->device_category,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => $maintenancePlan->next_due_date,
        ]);

        return redirect()->route('maintenance-orders.index')->with('status', '已依保養計畫「'.$maintenancePlan->name.'」建立保養工單');
    }
}
