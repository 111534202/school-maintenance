<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\DeviceCategory;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): View
    {
        $plans = MaintenancePlan::query()
            ->with(['deviceCategory', 'device.category'])
            ->withCount('maintenanceItems')
            // 關鍵字搜尋：計畫名稱、設備（編號、資產編號、品牌、型號）、設備類別名稱。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $like = '%' . trim($request->string('keyword')->toString()) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhereHas('device', fn ($device) => $device->where('device_code', 'like', $like)->orWhere('asset_code', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial_number', 'like', $like))
                    ->orWhereHas('deviceCategory', fn ($category) => $category->where('name', 'like', $like)));
            })
            ->orderByDesc('is_active')
            ->orderBy('next_due_date')
            ->paginate(15)
            ->withQueryString();

        return view('maintenance_plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('maintenance_plans.create', [
            'plan' => new MaintenancePlan(['is_active' => true]),
            'items' => MaintenanceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedItemIds' => [],
            'deviceCategories' => DeviceCategory::orderBy('name')->get(),
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
            ->with('success', '保養計畫新增成功');
    }

    public function edit(MaintenancePlan $maintenancePlan): View
    {
        return view('maintenance_plans.edit', [
            'plan' => $maintenancePlan,
            'items' => MaintenanceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedItemIds' => $maintenancePlan->maintenanceItems()->pluck('maintenance_items.id')->all(),
            'deviceCategories' => DeviceCategory::orderBy('name')->get(),
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
            ->with('success', '保養計畫更新成功');
    }

    /**
     * 停用／重新啟用（取代刪除）。
     */
    public function toggleStatus(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        $maintenancePlan->update(['is_active' => ! $maintenancePlan->is_active]);

        return redirect()
            ->route('maintenance-plans.index')
            ->with('success', $maintenancePlan->is_active ? '已重新啟用' : '已停用');
    }

    private function calculateNextDueDate(?Carbon $startDate, int $cycleDays): ?Carbon
    {
        return $startDate?->copy()->addDays($cycleDays);
    }
}
