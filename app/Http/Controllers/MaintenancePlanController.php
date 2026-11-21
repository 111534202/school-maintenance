<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\DeviceCategory;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePlan;
use App\Services\AuditLogger;
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
        AuditLogger::log('created', $plan, ['name' => $plan->name], '新增保養計畫「' . $plan->name . '」');

        return redirect()
            ->route('maintenance-plans.index')
            ->with('success', '保養計畫新增成功');
    }

    public function edit(MaintenancePlan $maintenancePlan): View|RedirectResponse
    {
        if ($maintenancePlan->isLocked()) {
            return $this->lockedRedirect();
        }

        return view('maintenance_plans.edit', [
            'plan' => $maintenancePlan,
            'items' => MaintenanceItem::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedItemIds' => $maintenancePlan->maintenanceItems()->pluck('maintenance_items.id')->all(),
            'deviceCategories' => DeviceCategory::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $maintenancePlan): RedirectResponse
    {
        if ($maintenancePlan->isLocked()) {
            return $this->lockedRedirect();
        }

        $data = $request->safe()->except('item_ids');
        $data['next_due_date'] = $this->calculateNextDueDate($request->date('start_date'), (int) $request->integer('cycle_days'));

        $maintenancePlan->update($data);
        $maintenancePlan->maintenanceItems()->sync($request->input('item_ids'));
        AuditLogger::log('updated', $maintenancePlan, ['name' => $maintenancePlan->name], '修改保養計畫「' . $maintenancePlan->name . '」');

        return redirect()
            ->route('maintenance-plans.index')
            ->with('success', '保養計畫更新成功');
    }

    /**
     * 停用／重新啟用（取代刪除）。
     */
    public function toggleStatus(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        if ($maintenancePlan->isLocked()) {
            return $this->lockedRedirect();
        }

        $maintenancePlan->update(['is_active' => ! $maintenancePlan->is_active]);
        AuditLogger::log('status_changed', $maintenancePlan, ['is_active' => $maintenancePlan->is_active], ($maintenancePlan->is_active ? '重新啟用' : '停用') . '保養計畫「' . $maintenancePlan->name . '」');

        return $this->backToList('maintenance-plans.index')
            ->with('success', $maintenancePlan->is_active ? '已重新啟用' : '已停用');
    }

    /** 計畫的設備已淘汰：資料已定案，不能修改、停用或重新啟用。 */
    private function lockedRedirect(): RedirectResponse
    {
        return redirect()->route('maintenance-plans.index')->with('error', '這份計畫的設備已淘汰，計畫已停用且資料已定案，不能修改。');
    }

    private function calculateNextDueDate(?Carbon $startDate, int $cycleDays): ?Carbon
    {
        return $startDate?->copy()->addDays($cycleDays);
    }
}
