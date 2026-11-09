<?php

namespace App\Services;

use App\Models\PartScrap;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RepairCostAnalysisService
{
    /**
     * 產生共用成本分析資料。
     *
     * 日期依據：repair_logs.created_at。
     * 成本依據：repair_part_usages.unit_price 歷史單價快照。
     * 案件範圍：沿用 RepairRequest::visibleTo()。
     * 報廢損失（篩選 scraps=include，預設）：依報廢日期篩選，金額＝各批數量×那一批的單價；
     * 報廢跟報修單無關，所以「案件狀態」篩選不影響報廢損失。
     */
    public function generate(User $user, array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $status = $filters['status'] ?? null;
        $includeScraps = ($filters['scraps'] ?? 'include') !== 'exclude';

        $scraps = $includeScraps
            ? PartScrap::query()
                ->with(['part:id,part_code,name,specification,unit', 'batches', 'recorder:id,name'])
                ->when($startDate, fn ($q) => $q->whereDate('scrap_date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('scrap_date', '<=', $endDate))
                ->orderByDesc('scrap_date')->orderByDesc('id')
                ->get()
            : collect();
        $scrapLoss = (int) $scraps->sum(fn (PartScrap $scrap) => $scrap->lossAmount());

        $usageQuery = $this->usageQuery(
            $user,
            $startDate,
            $endDate,
            $status
        );

        $partUsages = (clone $usageQuery)
            ->with([
                'part:id,name,unit',
                'repairLog:id,repair_request_id,created_at',
                'repairLog.repairRequest:id,title,status,device_id,device_note,classroom_id',
                'repairLog.repairRequest.classroom:id,classroom_code,name',
                'repairLog.repairRequest.device:id,device_code,asset_code,classroom_id',
                'repairLog.repairRequest.device.classroom:id,classroom_code,name',
            ])
            ->orderByDesc(
                RepairLog::query()
                    ->select('created_at')
                    ->whereColumn(
                        'repair_logs.id',
                        'repair_part_usages.repair_log_id'
                    )
            )
            ->get();

        $repairRequestCount = (clone $usageQuery)
            ->join(
                'repair_logs',
                'repair_logs.id',
                '=',
                'repair_part_usages.repair_log_id'
            )
            ->distinct()
            ->count('repair_logs.repair_request_id');

        $partUsageSummary = (clone $usageQuery)
            ->selectRaw('
                repair_part_usages.part_id,
                SUM(repair_part_usages.quantity) AS total_quantity,
                SUM(
                    CASE
                        WHEN repair_part_usages.unit_price IS NOT NULL
                        THEN repair_part_usages.quantity
                            * repair_part_usages.unit_price
                        ELSE 0
                    END
                ) AS known_subtotal,
                SUM(
                    CASE
                        WHEN repair_part_usages.unit_price IS NULL THEN 1
                        ELSE 0
                    END
                ) AS missing_price_count
            ')
            ->with('part:id,name,unit')
            ->groupBy('repair_part_usages.part_id')
            ->get();

        $knownSubtotal = $partUsages
            ->filter(fn ($usage) => $usage->unit_price !== null)
            ->sum(
                fn ($usage) => $usage->quantity * $usage->unit_price
            );

        $missingPriceCount = $partUsages
            ->filter(fn ($usage) => $usage->unit_price === null)
            ->count();

        return [
            'partUsages' => $partUsages,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'status' => $status,
            'knownSubtotal' => $knownSubtotal,
            'missingPriceCount' => $missingPriceCount,
            'hasIncompleteCost' => $missingPriceCount > 0,
            'repairRequestCount' => $repairRequestCount,
            'partUsageSummary' => $partUsageSummary,
            'includeScraps' => $includeScraps,
            'scraps' => $scraps,
            'scrapLoss' => $scrapLoss,
            'scrapCount' => $scraps->count(),
            'totalCost' => $knownSubtotal + $scrapLoss,
        ];
    }

    /**
     * 建立套用日期、狀態及使用者可見範圍的用料查詢。
     */
    private function usageQuery(
        User $user,
        ?string $startDate,
        ?string $endDate,
        ?string $status
    ): Builder {
        return RepairPartUsage::query()
            ->whereHas(
                'repairLog',
                function ($query) use (
                    $startDate,
                    $endDate,
                    $user,
                    $status
                ) {
                    if ($startDate !== null) {
                        $query->whereDate(
                            'created_at',
                            '>=',
                            $startDate
                        );
                    }

                    if ($endDate !== null) {
                        $query->whereDate(
                            'created_at',
                            '<=',
                            $endDate
                        );
                    }

                    $query->whereHas(
                        'repairRequest',
                        function ($repairRequests) use ($user, $status) {
                            $repairRequests->visibleTo($user);

                            if ($status !== null) {
                                $repairRequests->where('status', $status);
                            }
                        }
                    );
                }
            );
    }
}