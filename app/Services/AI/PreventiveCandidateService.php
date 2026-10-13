<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AI 預防保養候選的掃描／核准／駁回（第 4 週任務 2、3、4）。
 *
 * 流程：掃描所有「正常」設備 → 風險評分 → 資料不足／未達門檻／重複 就略過 →
 *   設定「需主管審核」（預設）：產生 pending 候選，等主管核准後才建 AI 來源工單；
 *   設定「不需審核」：直接建立 AI 來源工單，並留一筆 auto_created 候選當紀錄。
 * 這個預設（需審核）是工程實作決定，待全組確認，可在 AI 設定頁切換。
 */
class PreventiveCandidateService
{
    public function __construct(
        private readonly PredictionServiceInterface $predictor,
        private readonly MaintenanceOrderDeduplicator $deduplicator,
    ) {}

    /**
     * @return array{
     *     scanned: int,
     *     candidates_created: int,
     *     orders_created: int,
     *     skipped_insufficient_data: int,
     *     skipped_below_threshold: int,
     *     skipped_duplicate: int,
     *     details: array<int, array<string, mixed>>,
     * }
     */
    public function scan(): array
    {
        $threshold = (float) AiSetting::get(AiSetting::RISK_THRESHOLD);
        $requireApproval = (bool) AiSetting::get(AiSetting::REQUIRE_APPROVAL);

        $summary = [
            'scanned' => 0,
            'candidates_created' => 0,
            'orders_created' => 0,
            'skipped_insufficient_data' => 0,
            'skipped_below_threshold' => 0,
            'skipped_duplicate' => 0,
            'details' => [],
        ];

        Device::query()
            ->where('status', 'normal')
            ->orderBy('id')
            ->each(function (Device $device) use (&$summary, $threshold, $requireApproval) {
                $summary['scanned']++;
                $prediction = $this->predictor->predict($device);

                $detail = [
                    'device_code' => $device->device_code,
                    'risk_score' => $prediction['risk_score'],
                    'outcome' => null,
                ];

                if ($prediction['risk_score'] === null) {
                    $summary['skipped_insufficient_data']++;
                    $detail['outcome'] = '略過：保養歷史不足';
                } elseif ($prediction['risk_score'] < $threshold) {
                    $summary['skipped_below_threshold']++;
                    $detail['outcome'] = '略過：未達門檻';
                } elseif ($reason = $this->deduplicator->findDuplicateReason($device)) {
                    $summary['skipped_duplicate']++;
                    $detail['outcome'] = '略過：'.MaintenanceOrderDeduplicator::reasonLabel($reason);
                } else {
                    $candidate = $this->createCandidate($device, $prediction);

                    if ($requireApproval) {
                        $summary['candidates_created']++;
                        $detail['outcome'] = '已產生待審核候選';
                        AuditLogger::log('ai_candidate_created', $candidate, [
                            'risk_score' => $candidate->risk_score,
                            'threshold' => $candidate->threshold,
                        ], "AI 預防保養候選：{$device->device_code}");
                    } else {
                        $order = $this->createOrder($device);
                        $candidate->update([
                            'status' => PreventiveCandidate::STATUS_AUTO_CREATED,
                            'maintenance_order_id' => $order->id,
                            'decided_at' => Carbon::now(),
                            'decision_note' => '設定為不需審核，系統自動建立工單',
                        ]);
                        $summary['orders_created']++;
                        $detail['outcome'] = '已自動建立 AI 保養工單';
                        AuditLogger::log('ai_order_auto_created', $order, [
                            'risk_score' => $candidate->risk_score,
                        ], "AI 自動建立預防保養工單：{$device->device_code}");
                    }
                }

                $summary['details'][] = $detail;
            });

        return $summary;
    }

    /**
     * 主管核准：建立 AI 來源的保養工單。核准當下會再檢查一次重複
     * （候選產生後，可能已經有人手動建立了工單）。
     *
     * @return MaintenanceOrder|null 重複而未建單時回傳 null（候選會標記為 duplicate）
     */
    public function approve(PreventiveCandidate $candidate, ?User $by = null, ?string $note = null): ?MaintenanceOrder
    {
        return DB::transaction(function () use ($candidate, $by, $note) {
            $candidate = PreventiveCandidate::query()->lockForUpdate()->findOrFail($candidate->id);

            if ($candidate->status !== PreventiveCandidate::STATUS_PENDING) {
                throw new DomainException('這筆候選已經處理過了。');
            }

            $device = Device::withTrashed()->findOrFail($candidate->device_id);

            if ($reason = $this->deduplicator->findDuplicateReason($device, $candidate)) {
                $candidate->update([
                    'status' => PreventiveCandidate::STATUS_DUPLICATE,
                    'decided_by' => $by?->id,
                    'decided_at' => Carbon::now(),
                    'decision_note' => '核准時發現重複：'.MaintenanceOrderDeduplicator::reasonLabel($reason),
                ]);
                AuditLogger::log('ai_candidate_duplicate', $candidate, ['reason' => $reason], "AI 候選核准時發現重複：{$device->device_code}");

                return null;
            }

            $order = $this->createOrder($device);

            $candidate->update([
                'status' => PreventiveCandidate::STATUS_APPROVED,
                'maintenance_order_id' => $order->id,
                'decided_by' => $by?->id,
                'decided_at' => Carbon::now(),
                'decision_note' => $note,
            ]);
            AuditLogger::log('ai_candidate_approved', $candidate, ['maintenance_order_id' => $order->id], "核准 AI 預防保養候選：{$device->device_code}");

            return $order;
        });
    }

    public function reject(PreventiveCandidate $candidate, ?User $by = null, ?string $note = null): void
    {
        DB::transaction(function () use ($candidate, $by, $note) {
            $candidate = PreventiveCandidate::query()->lockForUpdate()->findOrFail($candidate->id);

            if ($candidate->status !== PreventiveCandidate::STATUS_PENDING) {
                throw new DomainException('這筆候選已經處理過了。');
            }

            $candidate->update([
                'status' => PreventiveCandidate::STATUS_REJECTED,
                'decided_by' => $by?->id,
                'decided_at' => Carbon::now(),
                'decision_note' => $note,
            ]);
            AuditLogger::log('ai_candidate_rejected', $candidate, [], "駁回 AI 預防保養候選（設備 #{$candidate->device_id}）");
        });
    }

    /**
     * @param  array<string, mixed>  $prediction
     */
    private function createCandidate(Device $device, array $prediction): PreventiveCandidate
    {
        return PreventiveCandidate::create([
            'device_id' => $device->id,
            'risk_score' => $prediction['risk_score'],
            'threshold' => $prediction['threshold'],
            'algorithm' => $prediction['algorithm'] ?? 'unknown',
            'explanation' => $prediction['explanation'] ?? [],
            'features' => $prediction['features'] ?? [],
            'status' => PreventiveCandidate::STATUS_PENDING,
        ]);
    }

    private function createOrder(Device $device): MaintenanceOrder
    {
        $device->loadMissing('category');

        return MaintenanceOrder::create([
            'maintenance_plan_id' => null,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => MaintenanceOrder::SOURCE_AI,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => Carbon::today(),
        ]);
    }
}
