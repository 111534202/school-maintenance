<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Services\AuditLogger;
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

    /**
     * 修改保養工單（GET /maintenance-orders/{id}/edit）：只能改「排定保養日期」，需要 maintenance.manage。
     * 已完成（已有保養結果）的工單不能再改。
     */
    public function edit(MaintenanceOrder $maintenanceOrder): View|RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成的保養工單不能修改。');
        }

        return view('maintenance_orders.edit', compact('maintenanceOrder'));
    }

    /** 儲存修改（PUT /maintenance-orders/{id}）：更新排定日期並寫操作紀錄。 */
    public function update(Request $request, MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成的保養工單不能修改。');
        }

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
        ], [
            'scheduled_date.required' => '請選擇排定保養日期。',
            'scheduled_date.date' => '排定保養日期格式不正確。',
        ]);

        $old = $maintenanceOrder->scheduled_date?->toDateString();
        $maintenanceOrder->update($validated);
        $new = $maintenanceOrder->scheduled_date?->toDateString();

        if ($old !== $new) {
            AuditLogger::log('updated', $maintenanceOrder, ['scheduled_date' => ['old' => $old, 'new' => $new]], '修改保養工單 #' . $maintenanceOrder->id . ' 的排定日期');
        }

        return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('success', '保養工單已修改。');
    }

    /**
     * 取消（刪除）保養工單（DELETE /maintenance-orders/{id}）：取消與刪除是同一件事，做法是軟刪除，
     * 列表與統計不再列出，資料與操作紀錄仍保留；排程不會因此重新產生同一期的工單。
     * 已完成或已經回報保養結果的工單不能取消（結果與用料已經記錄）。
     */
    public function destroy(MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED || $maintenanceOrder->result()->exists()) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成或已回報保養結果的工單不能取消。');
        }

        $id = $maintenanceOrder->id;
        $maintenanceOrder->delete();

        AuditLogger::log('deleted', $maintenanceOrder, ['status' => $maintenanceOrder->status], '取消（刪除）保養工單 #' . $id);

        return redirect()->route('maintenance-orders.index')->with('success', '保養工單 #' . $id . ' 已取消。');
    }
}
