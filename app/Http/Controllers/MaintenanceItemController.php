<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceItemRequest;
use App\Http\Requests\UpdateMaintenanceItemRequest;
use App\Models\MaintenanceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 保養項目 CRUD。
 *
 * 第 1 週任務 1：完成 maintenance_items migration/model、CRUD、validation 與 Seeder。
 * 完成證據：保養項目可新增/修改/停用（停用採 is_active 切換，不做硬刪除，
 * 避免已被保養計畫引用的項目被刪除後產生資料斷鏈）。
 */
class MaintenanceItemController extends Controller
{
    public function index(): View
    {
        $items = MaintenanceItem::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);

        return view('maintenance_items.index', compact('items'));
    }

    public function create(): View
    {
        return view('maintenance_items.create', [
            'item' => new MaintenanceItem(['is_active' => true]),
        ]);
    }

    public function store(StoreMaintenanceItemRequest $request): RedirectResponse
    {
        MaintenanceItem::create($request->validated());

        return redirect()
            ->route('maintenance-items.index')
            ->with('status', '保養項目新增成功');
    }

    public function edit(MaintenanceItem $maintenanceItem): View
    {
        return view('maintenance_items.edit', ['item' => $maintenanceItem]);
    }

    public function update(UpdateMaintenanceItemRequest $request, MaintenanceItem $maintenanceItem): RedirectResponse
    {
        $maintenanceItem->update($request->validated());

        return redirect()
            ->route('maintenance-items.index')
            ->with('status', '保養項目更新成功');
    }

    /**
     * 停用／重新啟用（取代刪除）。
     */
    public function toggleStatus(MaintenanceItem $maintenanceItem): RedirectResponse
    {
        $maintenanceItem->update(['is_active' => ! $maintenanceItem->is_active]);

        return redirect()
            ->route('maintenance-items.index')
            ->with('status', $maintenanceItem->is_active ? '已重新啟用' : '已停用');
    }
}
