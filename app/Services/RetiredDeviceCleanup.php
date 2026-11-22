<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use App\Models\RepairRequest;
use Illuminate\Support\Facades\DB;

/**
 * 設備淘汰時的收尾：把這台設備所有「進行中」的東西一次中止，之後全部唯讀。
 *
 * 已淘汰是定案，設備不會再使用，所以和它綁在一起、還沒做完的事都沒有意義了：
 * - 進行中的報修單（新報修、已派工、處理中、待驗收）→ 已中止；
 * - 還沒做完的保養工單（待處理、進行中）→ 已中止；
 * - 綁定這台設備的保養計畫 → 停用（之後不能修改、不能重新啟用）；
 * - 還在等審核的 AI 預防保養候選 → 駁回；
 * - 保養 NG 還沒轉報修的 → 標成「不需轉報修」（設備已淘汰，不會再有報修單）。
 * 已結案、已完成、已有結果的歷史紀錄原樣保留（維修紀錄、用料、成本都不動）。
 *
 * 呼叫的地方只有 DeviceStatusService::updateStatus（設備狀態改成已淘汰的當下）與一次性的資料整理 migration。
 */
class RetiredDeviceCleanup
{
    public function __construct(private RepairRequestWorkflow $workflow) {}

    /**
     * @return array{repairs: int, orders: int, plans: int, candidates: int, ng_results: int}
     */
    public function run(Device $device): array
    {
        $note = '設備 ' . $device->device_code . ' 已淘汰';

        return DB::transaction(function () use ($device, $note): array {
            $summary = ['repairs' => 0, 'orders' => 0, 'plans' => 0, 'candidates' => 0, 'ng_results' => 0];

            $repairs = RepairRequest::query()
                ->where('device_id', $device->id)
                ->whereIn('status', RepairRequestStatus::openValues())
                ->lockForUpdate()
                ->get();
            foreach ($repairs as $repair) {
                $from = $repair->status->value;
                $this->workflow->abort($repair);
                AuditLogger::log('status_changed', $repair, ['from' => $from, 'to' => RepairRequestStatus::Aborted->value], $note . '，報修單「' . $repair->title . '」自動中止');
                $summary['repairs']++;
            }

            $orders = MaintenanceOrder::query()
                ->where('device_id', $device->id)
                ->unfinished()
                ->lockForUpdate()
                ->get();
            foreach ($orders as $order) {
                $from = $order->status;
                $order->update(['status' => MaintenanceOrder::STATUS_ABORTED]);
                AuditLogger::log('status_changed', $order, ['from' => $from, 'to' => MaintenanceOrder::STATUS_ABORTED], $note . '，保養工單 ' . $order->displayNumber() . ' 自動中止');
                $summary['orders']++;
            }

            $plans = MaintenancePlan::query()->where('device_id', $device->id)->where('is_active', true)->lockForUpdate()->get();
            foreach ($plans as $plan) {
                $plan->update(['is_active' => false]);
                AuditLogger::log('status_changed', $plan, ['is_active' => false], $note . '，保養計畫「' . $plan->name . '」自動停用');
                $summary['plans']++;
            }

            $candidates = PreventiveCandidate::query()
                ->where('device_id', $device->id)
                ->where('status', PreventiveCandidate::STATUS_PENDING)
                ->lockForUpdate()
                ->get();
            foreach ($candidates as $candidate) {
                $candidate->update([
                    'status' => PreventiveCandidate::STATUS_REJECTED,
                    'decided_at' => now(),
                    'decision_note' => $note . '，候選自動駁回',
                ]);
                AuditLogger::log('ai_candidate_rejected', $candidate, [], $note . '，AI 預防保養候選自動駁回');
                $summary['candidates']++;
            }

            $ngResults = MaintenanceResult::query()
                ->where('ng_conversion_status', MaintenanceResult::NG_CONVERSION_PENDING)
                ->whereHas('maintenanceOrder', fn ($order) => $order->withTrashed()->where('device_id', $device->id))
                ->lockForUpdate()
                ->get();
            foreach ($ngResults as $result) {
                $result->update(['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE]);
                AuditLogger::log('updated', $result, ['ng_conversion_status' => MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE], $note . '，保養 NG 不再轉報修');
                $summary['ng_results']++;
            }

            return $summary;
        });
    }
}
