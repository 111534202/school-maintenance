<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use App\Models\RepairRequest;
use App\Services\AI\PredictionServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * 設備履歷頁（第 3 週任務 2）。
 *
 * 以「設備」為中心，彙總跟這台設備有關的各模組資料做唯讀呈現：
 *   - 基本資料：devices（林政寬主責），唯讀查詢。
 *   - 保養結果：maintenance_orders / maintenance_results（本模組主責），依 device_id 篩選。
 *   - 報修、維修紀錄、附件：彭仕衡主責的模組（已併入），唯讀查詢他的 RepairRequest / RepairLog / Attachment，
 *     並沿用他的歸屬權限規則（RepairRequest::visibleTo）：使用者只看得到自己有權限檢視的報修單，
 *     設備履歷不會變成繞過報修單權限的後門。不另外建表、不複製資料。
 *   - 備品耗用成本：劉家芸主責的模組尚未併入，先以維修紀錄上的「使用備品說明」文字呈現，成本欄位待她的介面確認。
 *   - AI 預測（第 4 週）：即時呼叫 PredictionServiceInterface 取得風險分數與評分明細，並列出預防保養候選。
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
    public function show(Request $request, Device $device, PredictionServiceInterface $predictor): View
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

        // 第 4 週任務 5：AI 風險評分（即時計算，不寫入資料庫）與這台設備的預防保養候選歷史。
        $prediction = $predictor->predict($device);

        $candidates = PreventiveCandidate::query()
            ->where('device_id', $device->id)
            ->with(['maintenanceOrder', 'decider'])
            ->orderByDesc('id')
            ->get();

        // 第 5 週任務 3：報修／維修紀錄／附件（彭仕衡的模組，唯讀）。
        // visibleTo：只撈「這位使用者有權限檢視」的報修單（規則見 RepairRequestPolicy），其餘只顯示被隱藏的筆數。
        $repairRequests = RepairRequest::visibleTo($request->user())
            ->where('device_id', $device->id)
            ->with(['repairLogs.attachments', 'attachments', 'reporter', 'assignedTechnician'])
            ->orderByDesc('id')
            ->get();

        $hiddenRepairCount = RepairRequest::where('device_id', $device->id)->count() - $repairRequests->count();

        // 由保養 NG 轉入的報修單：保養結果 → 報修單的追溯（key 是報修單 id）。
        $ngSources = MaintenanceResult::query()
            ->whereIn('repair_request_id', $repairRequests->pluck('id'))
            ->with('maintenanceOrder')
            ->get()
            ->keyBy('repair_request_id');

        $repairStats = [
            'total' => $repairRequests->count(),
            'open' => $repairRequests->filter(fn (RepairRequest $r) => $r->status !== \App\Enums\RepairRequestStatus::Completed)->count(),
            'hours' => (float) $repairRequests->flatMap->repairLogs->sum('total_hours'),
        ];

        // 這台設備所有附件（報修單照片 + 各筆維修紀錄的前後照片），每個附件只列一次。
        $attachments = $repairRequests->flatMap(function (RepairRequest $r) {
            $own = $r->attachments->map(fn ($a) => ['file' => $a, 'from' => "報修單 #{$r->id}", 'repair' => $r]);
            $fromLogs = $r->repairLogs->flatMap(
                fn ($log) => $log->attachments->map(fn ($a) => ['file' => $a, 'from' => "報修單 #{$r->id} 維修紀錄", 'repair' => $r])
            );

            return $own->concat($fromLogs);
        })->values();

        return view('device_profile.show', compact(
            'device', 'maintenanceOrders', 'maintenanceStats', 'prediction', 'candidates',
            'repairRequests', 'hiddenRepairCount', 'ngSources', 'repairStats', 'attachments'
        ));
    }
}
