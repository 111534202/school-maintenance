<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 保養計畫 CRUD（第 1 週任務 2、3）。
 *
 * 完成證據：計畫與項目關聯可查（任務2）、計畫管理可操作（任務3）。
 * 停用一樣採 is_active 切換，不做硬刪除。
 */
class MaintenancePlanController extends Controller
{
    public function index(): View
    {
        $plans = MaintenancePlan::query()
            ->withCount('maintenanceItems')
            ->orderByDesc('is_active')
            ->orderBy('next_due_date')
            ->paginate(15);

        return view('maintenance_plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('maintenance_plans.create', [
            'plan' => new MaintenancePlan(['is_active' => true]),
            'items' => MaintenanceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedItemIds' => [],
        ]);
    }

    public function store(StoreMaintenancePlanRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('item_ids');
        $data['next_due_date'] = $this->calculateNextDueDate($request->date('start_date'), (int) $request->integer('cycle_days'));

        $plan = MaintenancePlan::create($data);
        $plan->maintenanceItems()->sync($request->input('item_ids'));

        return redirect()
            ->route('maintenance-plans.index')
            ->with('status', '保養計畫新增成功');
    }

    public function edit(MaintenancePlan $maintenancePlan): View
    {
        return view('maintenance_plans.edit', [
            'plan' => $maintenancePlan,
            'items' => MaintenanceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedItemIds' => $maintenancePlan->maintenanceItems()->pluck('maintenance_items.id')->all(),
        ]);
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $maintenancePlan): RedirectResponse
    {
        $data = $request->safe()->except('item_ids');
        $data['next_due_date'] = $this->calculateNextDueDate($request->date('start_date'), (int) $request->integer('cycle_days'));

        $maintenancePlan->update($data);
        $maintenancePlan->maintenanceItems()->sync($request->input('item_ids'));

        return redirect()
            ->route('maintenance-plans.index')
            ->with('status', '保養計畫更新成功');
    }

    /**
     * 停用／重新啟用（取代刪除）。
     */
    public function toggleStatus(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        $maintenancePlan->update(['is_active' => ! $maintenancePlan->is_active]);

        return redirect()
            ->route('maintenance-plans.index')
            ->with('status', $maintenancePlan->is_active ? '已重新啟用' : '已停用');
    }

    private function calculateNextDueDate(?Carbon $startDate, int $cycleDays): ?Carbon
    {
        return $startDate?->copy()->addDays($cycleDays);
    }
}
