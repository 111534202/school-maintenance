<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Models\Device;
use App\Models\DeviceCategory;
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
            // 關鍵字搜尋：計畫名稱、設備（編號、資產編號、品牌、型號）、設備類別名稱。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $like = '%' . trim($request->string('keyword')->toString()) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like)
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
        return view('maintenance_plans.create', ['plan' => new MaintenancePlan(['is_active' => true])] + $this->targetOptions());
    }

    public function store(StoreMaintenancePlanRequest $request): RedirectResponse
    {
        $data = $request->planData();
        $data['next_due_date'] = $this->calculateNextDueDate($request->date('start_date'), (int) $request->integer('cycle_days'));

        $plan = MaintenancePlan::create($data);
        AuditLogger::log('created', $plan, ['name' => $plan->name], '新增保養計畫「' . $plan->name . '」');

        return redirect()
            ->route('maintenance-plans.index')
            ->with('success', '保養計畫新增成功' . $this->generateNow($plan));
    }

    public function edit(MaintenancePlan $maintenancePlan): View
    {
        return view('maintenance_plans.edit', ['plan' => $maintenancePlan] + $this->targetOptions($maintenancePlan));
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $maintenancePlan): RedirectResponse
    {
        $data = $request->planData();
        // 改了起始日或週期才重算下次到期日（起始日＋週期）；只改名稱、說明、對象時保持原本的下次到期日，
        // 不然已經產生過的工單又會被重新排一次。
        $scheduleChanged = $maintenancePlan->start_date?->toDateString() !== $request->date('start_date')?->toDateString()
            || (int) $maintenancePlan->cycle_days !== (int) $request->integer('cycle_days');
        if ($scheduleChanged || $maintenancePlan->next_due_date === null) {
            $data['next_due_date'] = $this->calculateNextDueDate($request->date('start_date'), (int) $request->integer('cycle_days'));
        }

        $maintenancePlan->update($data);
        AuditLogger::log('updated', $maintenancePlan, ['name' => $maintenancePlan->name], '修改保養計畫「' . $maintenancePlan->name . '」');

        return redirect()
            ->route('maintenance-plans.index')
            ->with('success', '保養計畫更新成功' . $this->generateNow($maintenancePlan));
    }

    /**
     * 停用／重新啟用（取代刪除）。
     */
    public function toggleStatus(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        $maintenancePlan->update(['is_active' => ! $maintenancePlan->is_active]);
        AuditLogger::log('status_changed', $maintenancePlan, ['is_active' => $maintenancePlan->is_active], ($maintenancePlan->is_active ? '重新啟用' : '停用') . '保養計畫「' . $maintenancePlan->name . '」');

        return $this->backToList('maintenance-plans.index')
            ->with('success', $maintenancePlan->is_active ? '已重新啟用' : '已停用');
    }

    /**
     * 「設備／設備類別」可搜尋下拉選單的選項：先列設備類別，再列設備（不含已淘汰；修改時目前選的那台就算已淘汰也照樣列出）。
     * 每個選項的值是 category:編號 或 device:編號，存檔時由 StoreMaintenancePlanRequest 拆回兩個欄位（只會有一個有值）。
     *
     * @return array{targets: \Illuminate\Support\Collection<int, array{value: string, label: string, search: string, group: string}>}
     */
    private function targetOptions(?MaintenancePlan $plan = null): array
    {
        $categories = \App\Support\StrokeOrder::sort(DeviceCategory::query()->get())->map(fn (DeviceCategory $category) => [
            'value' => 'category:' . $category->id,
            'label' => $category->name,
            'search' => mb_strtolower($category->name),
            'group' => '設備類別',
        ]);

        $keptDeviceIds = $plan?->device_id ? [$plan->device_id] : [];
        $devices = Device::query()->with(['category', 'classroom'])
            ->where(fn ($query) => $query->where('status', '<>', 'retired')->when($keptDeviceIds, fn ($q, $ids) => $q->orWhereIn('id', $ids)))
            ->orderBy('device_code')->get()
            ->map(function (Device $device) {
                $detail = trim(($device->category?->name ?? '') . ' ' . ($device->classroom?->name ?? ''));
                $label = $device->device_code . ($detail !== '' ? '（' . $detail . '）' : '');

                return ['value' => 'device:' . $device->id, 'label' => $label, 'search' => mb_strtolower($label . ' ' . $device->brand . ' ' . $device->model), 'group' => '設備'];
            });

        return ['targets' => $categories->concat($devices)->values()];
    }

    /**
     * 存檔後馬上檢查這個計畫要不要產生工單（不用等半夜的排程）：下次到期日就是今天、而且勾選啟用，
     * 就產生排定今天的工單（規則見 DueMaintenanceOrderGenerator；下次到期日要等工單做完或取消才往後推）。
     * 回傳要接在成功訊息後面的說明。
     */
    private function generateNow(MaintenancePlan $plan): string
    {
        $orders = app(\App\Services\DueMaintenanceOrderGenerator::class)->generateForPlan($plan);
        if ($orders->isEmpty()) {
            return '';
        }

        return '；已產生保養工單 ' . $orders->map(fn ($order) => $order->displayNumber() . '（排定 ' . $order->scheduled_date?->toDateString() . '）')->implode('、');
    }

    private function calculateNextDueDate(?Carbon $startDate, int $cycleDays): ?Carbon
    {
        return $startDate?->copy()->addDays($cycleDays);
    }
}
