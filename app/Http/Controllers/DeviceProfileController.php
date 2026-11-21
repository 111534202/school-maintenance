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
 *   - 零件使用成本：維修紀錄、保養結果登記的零件（數量合併；金額只給零件庫存管理者看）。
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

        // 包含這台設備的保養工單：工單本身的設備，或保養對象清單裡有這台設備（一張工單可以有好幾台設備）。
        $maintenanceOrders = MaintenanceOrder::query()
            ->coveringDevice($device->id)
            ->with(['result.partUsages.part', 'maintenancePlan'])
            ->orderByDesc('scheduled_date')
            ->get();

        $maintenanceStats = [
            'total' => $maintenanceOrders->count(),
            // 用 Model 上已經寫好的 completed scope（本來就是給設備履歷、Dashboard 這類其他模組用的穩定介面）。
            'completed' => MaintenanceOrder::query()->coveringDevice($device->id)->completed()->count(),
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
        // 已退回的報修單不算進設備履歷（退回＝不需要維修或不是這台設備的問題，流程已結束；要修的話會重新報修）。
        $repairRequests = RepairRequest::visibleOnBoard($request->user())   // 待驗收／已結案只給跟案件有關的人看，和看板一致
            ->where('device_id', $device->id)
            ->where('status', '<>', \App\Enums\RepairRequestStatus::Returned->value)
            ->with(['repairLogs.attachments', 'repairLogs.partUsages.part', 'attachments', 'requester', 'assignedTechnician'])
            ->orderByDesc('id')
            ->get();

        $hiddenRepairCount = RepairRequest::where('device_id', $device->id)->where('status', '<>', \App\Enums\RepairRequestStatus::Returned->value)->count() - $repairRequests->count();

        // 由保養 NG 轉入的報修單：保養結果 → 報修單的追溯（key 是報修單 id）。
        $ngSources = MaintenanceResult::query()
            ->whereIn('repair_request_id', $repairRequests->pluck('id'))
            ->with('maintenanceOrder')
            ->get()
            ->keyBy('repair_request_id');

        $repairStats = [
            'total' => $repairRequests->count(),
            'open' => $repairRequests->filter(fn (RepairRequest $r) => ! $r->status->isClosed())->count(),
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

        // 零件使用成本：這台設備的維修紀錄、保養結果登記的零件（同一個零件合併數量；金額只給零件庫存管理者看）。
        $partRows = collect();
        foreach ($repairRequests as $r) {
            foreach ($r->repairLogs as $log) {
                foreach ($log->partUsages as $usage) {
                    $partRows->push(['part' => $usage->part, 'quantity' => $usage->quantity, 'amount' => $usage->quantity * (int) $usage->unit_price, 'source' => '報修單 #' . $r->id,
                        'date' => ($log->ended_at ?? $log->started_at ?? $log->created_at)?->format('Y-m-d'), 'url' => route('repairs.show', $r), 'title' => '報修案件']);
                }
            }
        }
        foreach ($maintenanceOrders as $order) {
            foreach ($order->result?->partUsages ?? [] as $usage) {
                $partRows->push(['part' => $usage->part, 'quantity' => $usage->quantity, 'amount' => $usage->quantity * (int) $usage->unit_price, 'source' => '保養工單 ' . $order->displayNumber(),
                    'date' => ($order->result->executed_at ?? $order->result->created_at)?->format('Y-m-d'), 'url' => route('maintenance-orders.show', $order), 'title' => '保養紀錄']);
            }
        }
        $partUsage = $partRows->groupBy(fn ($row) => $row['part']?->id ?? 0)->map(fn ($rows) => [
            'part' => $rows->first()['part'],
            'quantity' => $rows->sum('quantity'),
            'amount' => $rows->sum('amount'),
            'sources' => $rows->pluck('source')->unique()->values(),
            // 每一筆使用（點零件時跳出的視窗列這些；金額只給零件庫存管理者，見畫面）。
            'uses' => $rows->sortByDesc('date')->map(fn ($row) => [
                'date' => $row['date'], 'source' => $row['source'], 'url' => $row['url'], 'title' => $row['title'],
                'quantity' => $row['quantity'] . ' ' . ($row['part']?->unit ?? ''),
                'amount' => $request->user()->can('parts.manage') ? number_format($row['amount']) . ' 元' : null,
            ])->values()->all(),
        ])->values();
        $canSeePartCost = $request->user()->can('parts.manage');

        return view('device_profile.show', compact(
            'partUsage', 'canSeePartCost',
            'device', 'maintenanceOrders', 'maintenanceStats', 'prediction', 'candidates',
            'repairRequests', 'hiddenRepairCount', 'ngSources', 'repairStats', 'attachments'
        ));
    }
}
