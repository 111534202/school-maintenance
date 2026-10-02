<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * 設備履歷頁（第 3 週任務 2）。
 *
 * 以「設備」為中心，彙總跟這台設備有關的各模組資料做唯讀呈現：
 *   - 基本資料：devices（林政寬主責），唯讀查詢。
 *   - 保養結果：maintenance_orders / maintenance_results（本模組主責），依 device_id 篩選。
 *   - 報修、維修、備品耗用成本：分別是彭仕衡、劉家芸主責的模組，分支尚未併入 develop，
 *     目前先用提示文字呈現「尚未串接」，等對方分支併入後改成唯讀查詢他們的 Model，
 *     不會在這裡另外建表或複製資料（對應《個人工作計畫》規則：跨模組資料只能讀，不能複製）。
 *   - 附件：共用附件機制還沒建立（排在第 3 週任務 3 之後），先用提示文字呈現。
 */
class DeviceProfileController extends Controller
{
    /**
     * 設備列表，作為選擇要查看哪一台設備履歷的入口，支援關鍵字搜尋。
     */
    public function index(Request $request): View
    {
        $devices = Device::query()
            ->with(['category', 'classroom'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword');

                $query->where(function ($inner) use ($keyword) {
                    $inner->where('device_code', 'like', "%{$keyword}%")
                        ->orWhere('asset_code', 'like', "%{$keyword}%")
                        ->orWhere('brand', 'like', "%{$keyword}%")
                        ->orWhere('model', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('device_code')
            ->paginate(15)
            ->withQueryString();

        return view('device_profile.index', compact('devices'));
    }

    /**
     * 單一設備的履歷彙總頁（唯讀）。
     */
    public function show(Device $device): View
    {
        $device->load(['category', 'classroom']);

        $maintenanceOrders = MaintenanceOrder::query()
            ->where('device_id', $device->id)
            ->with(['result', 'maintenancePlan'])
            ->orderByDesc('scheduled_date')
            ->get();

        $maintenanceStats = [
            'total' => $maintenanceOrders->count(),
            // 用 Model 上已經寫好的 completed scope（本來就是給設備履歷、Dashboard 這類其他模組用的穩定介面）。
            'completed' => MaintenanceOrder::query()->where('device_id', $device->id)->completed()->count(),
            'ok' => $maintenanceOrders->filter(fn (MaintenanceOrder $order) => $order->result && ! $order->result->isNg())->count(),
            'ng' => $maintenanceOrders->filter(fn (MaintenanceOrder $order) => $order->result?->isNg())->count(),
        ];

        return view('device_profile.show', compact('device', 'maintenanceOrders', 'maintenanceStats'));
    }
}
