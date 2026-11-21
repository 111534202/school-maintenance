<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceResultRequest;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\Part;
use App\Models\RepairRequest;
use App\Services\AttachmentUploader;
use App\Services\AuditLogger;
use App\Services\NgToRepairService;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceResultController extends Controller
{
    /**
     * 保養結果回報表單（第 2 週任務）。
     */
    public function create(MaintenanceOrder $maintenanceOrder): View|RedirectResponse
    {
        if ($redirect = $this->guard($maintenanceOrder)) {
            return $redirect;
        }

        // 保養用料選單：列出全部零件（沒有庫存的顯示庫存 0、不能選），和維修紀錄的選法相同。
        $parts = Part::query()->orderBy('id')->get();
        // NG 時可以選的設備：計畫是一台設備就是那台；計畫是設備類別就是那一類的設備（可複選）。
        $ngDevices = app(NgToRepairService::class)->selectableDevices($maintenanceOrder);

        return view('maintenance_results.create', ['order' => $maintenanceOrder, 'parts' => $parts, 'ngDevices' => $ngDevices, 'skippedDevices' => app(NgToRepairService::class)->skippedDevices($maintenanceOrder)]);
    }

    /**
     * 保養結果詳細頁（第 3 週任務 3）：原本結果只以卡片形式嵌在工單詳細頁裡，
     * 這裡補一個獨立的詳細頁，之後附件共用機制正式納入時，就在這個頁面上顯示附件，
     * 不用再改工單詳細頁。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        abort_unless($maintenanceOrder->result()->exists(), 404);

        $maintenanceOrder->load(['result.partUsages.part', 'result.attachments', 'result.repairRequests', 'maintenancePlan']);

        return view('maintenance_results.show', ['order' => $maintenanceOrder, 'result' => $maintenanceOrder->result, 'skippedDevices' => app(NgToRepairService::class)->skippedDevices($maintenanceOrder)]);
    }

    /**
     * 儲存保養結果，並將工單標記為已完成；若結果為 NG，交給 NgToRepairService 轉報修。
     */
    public function store(StoreMaintenanceResultRequest $request, MaintenanceOrder $maintenanceOrder, NgToRepairService $ngToRepairService, PartStockService $stock, AttachmentUploader $attachmentUploader): RedirectResponse
    {
        if ($redirect = $this->guard($maintenanceOrder)) {
            return $redirect;
        }

        $validated = $request->validated();
        $partUsages = $validated['part_usages'] ?? [];
        $isNg = $validated['result'] === MaintenanceResult::RESULT_NG;

        // NG 的設備只能從這張工單可選的設備裡選；OK 不轉報修，忽略送來的設備。
        $ngDeviceIds = [];
        if ($isNg) {
            $allowed = $ngToRepairService->selectableDevices($maintenanceOrder)->pluck('id')->all();
            $ngDeviceIds = array_values(array_unique(array_map('intval', $validated['ng_device_ids'] ?? [])));
            if ($ngDeviceIds === [] || array_diff($ngDeviceIds, $allowed) !== []) {
                throw ValidationException::withMessages(['ng_device_ids' => '請從清單中選擇這張工單的異常設備。']);
            }
        }
        unset($validated['part_usages'], $validated['attachments'], $validated['ng_device_ids']);

        // 保養結果、用料扣庫存、NG 轉報修、工單狀態放在同一個交易：任何一個零件庫存不足，整筆都不會寫入。
        [$result, $repairs] = DB::transaction(function () use ($validated, $partUsages, $request, $maintenanceOrder, $stock, $attachmentUploader, $ngToRepairService, $ngDeviceIds, $isNg) {
            $result = MaintenanceResult::create([
                ...$validated,
                'executed_by' => $request->user()->id,   // 執行人 = 登入帳號（外鍵 users.id），不能自己亂填
                'maintenance_order_id' => $maintenanceOrder->id,
            ]);

            foreach ($partUsages as $index => $usage) {
                if (empty($usage['part_id'])) {
                    continue;   // 空白列代表沒有選零件
                }

                $part = Part::query()->findOrFail((int) $usage['part_id']);

                // 依先進先出扣庫存；跨批次時一批建立一列用料明細。
                try {
                    $allocations = $stock->consume($part, (int) $usage['quantity']);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(["part_usages.{$index}.quantity" => $exception->getMessage()]);
                }

                foreach ($allocations as $allocation) {
                    $result->partUsages()->create(['part_id' => $part->id, ...$allocation]);
                }

                // 依日期重新計算先進先出（執行時間比較早時可能改扣更早的批次）；會讓某一筆當時庫存不足就整筆擋下。
                try {
                    $stock->rebuild($part);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(["part_usages.{$index}.quantity" => $exception->getMessage()]);
                }
            }

            // 附件（保養前後照片等）：和報修共用同一個上傳服務與限制（最多 5 個、加起來 5 MB）。
            if ($request->hasFile('attachments')) {
                $attachmentUploader->storeMany($result, $request->file('attachments'));
            }

            // OK：整張工單完成。NG：每台 NG 的設備各建一張報修單，工單「已轉報修」，報修單全部結案後才完成。
            $repairs = $ngToRepairService->convert($result, $ngDeviceIds);
            $maintenanceOrder->update(['status' => $isNg ? MaintenanceOrder::STATUS_CONVERTED : MaintenanceOrder::STATUS_COMPLETED]);

            return [$result, $repairs];
        });


        AuditLogger::log('created', $result, ['result' => $result->result, 'maintenance_order_id' => $maintenanceOrder->id], '回報保養結果：工單 ' . $maintenanceOrder->displayNumber() . '，結果 ' . ($result->isNg() ? 'NG' : 'OK'));

        $status = $isNg
            ? '保養結果已回報為 NG，已建立 ' . $repairs->count() . ' 張報修單（' . $repairs->map(fn (RepairRequest $repair) => '#' . $repair->id)->implode('、') . '），工單改為已轉報修；報修單全部結案後工單會自動完成。'
            : '保養結果已回報為 OK，工單已完成';

        return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('success', $status);
    }

    /**
     * 回報前的檢查：已經回報過、還沒確認派工時導回工單頁並用中文說明；不是負責人（也不是保養管理者）就 403。
     */
    private function guard(MaintenanceOrder $maintenanceOrder): ?RedirectResponse
    {
        // 已經回報過（例如按上一頁又重按、開了兩個分頁）：導回工單頁並用中文說明，不要出現英文錯誤頁。
        // 已完成、已轉報修的工單是定案（就算舊資料沒有結果那一筆）：不能再回報。
        if ($maintenanceOrder->result()->exists() || in_array($maintenanceOrder->status, [MaintenanceOrder::STATUS_COMPLETED, MaintenanceOrder::STATUS_CONVERTED], true)) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '這張工單已經回報過保養結果了。');
        }
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_PENDING) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '這張工單還沒確認派工，請保養管理者先確認派工。');
        }
        abort_unless($maintenanceOrder->canBeReportedBy(request()->user()), 403, '這張工單已派工給別人，只有負責人與保養管理者可以回報。');

        return null;
    }
}
