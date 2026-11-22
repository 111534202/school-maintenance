<?php

namespace App\Services;

use App\Models\MaintenancePartUsage;
use App\Models\PartPurchase;
use App\Models\PartScrap;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
     * 保養用料：併入材料費（日期依保養結果的執行時間）；「案件狀態」是報修單的條件，選了狀態就只看維修用料。
     * 進貨成本：依進貨日期篩選，金額＝數量×單價，含新增零件時填的期初庫存（報廢與用料可能就是從期初庫存扣的，不含的話進貨會比報廢還少）；
     * 進貨是「買進來」、材料費是「用掉了」，兩者不相加，避免重複計算。
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

        $repairSubtotal = $partUsages
            ->filter(fn ($usage) => $usage->unit_price !== null)
            ->sum(
                fn ($usage) => $usage->quantity * $usage->unit_price
            );

        $repairMissingCount = $partUsages
            ->filter(fn ($usage) => $usage->unit_price === null)
            ->count();

        // 保養用料：跟維修用料用同一套算法（歷史單價；缺單價不當成 0 元）。
        $maintenanceUsages = $status === null
            ? $this->maintenanceUsages($startDate, $endDate)
            : collect();
        $maintenanceSubtotal = $maintenanceUsages
            ->filter(fn ($usage) => $usage->unit_price !== null)
            ->sum(fn ($usage) => $usage->quantity * $usage->unit_price);
        $maintenanceMissingCount = $maintenanceUsages
            ->filter(fn ($usage) => $usage->unit_price === null)
            ->count();

        $knownSubtotal = $repairSubtotal + $maintenanceSubtotal;
        $missingPriceCount = $repairMissingCount + $maintenanceMissingCount;

        $partUsageSummary = $this->mergeMaintenanceIntoSummary($partUsageSummary, $maintenanceUsages);

        $purchases = $this->purchases($startDate, $endDate);
        $purchaseTotal = (int) $purchases->sum(fn (PartPurchase $purchase) => $purchase->totalPrice());
        $purchaseOpeningTotal = (int) $purchases
            ->filter(fn (PartPurchase $purchase) => $purchase->isOpeningStock())
            ->sum(fn (PartPurchase $purchase) => $purchase->totalPrice());

        return [
            'partUsages' => $partUsages,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'status' => $status,
            'knownSubtotal' => $knownSubtotal,
            'repairSubtotal' => $repairSubtotal,
            'maintenanceSubtotal' => $maintenanceSubtotal,
            'maintenanceUsages' => $maintenanceUsages,
            'purchases' => $purchases,
            'purchaseTotal' => $purchaseTotal,
            'purchaseOpeningTotal' => $purchaseOpeningTotal,
            'purchaseCount' => $purchases->count(),
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
     * 期間內的保養用料明細（日期依保養結果的執行時間），新的在前。
     *
     * @return \Illuminate\Support\Collection<int, MaintenancePartUsage>
     */
    private function maintenanceUsages(?string $startDate, ?string $endDate)
    {
        return MaintenancePartUsage::query()
            ->whereHas('maintenanceResult', function ($query) use ($startDate, $endDate) {
                $query
                    ->when($startDate, fn ($q) => $q->whereDate('executed_at', '>=', $startDate))
                    ->when($endDate, fn ($q) => $q->whereDate('executed_at', '<=', $endDate));
            })
            ->with([
                'part:id,name,unit',
                'maintenanceResult:id,maintenance_order_id,executed_at',
                'maintenanceResult.maintenanceOrder:id,source,device_id,device_category_id',
                'maintenanceResult.maintenanceOrder.device:id,device_code,asset_code,classroom_id',
                'maintenanceResult.maintenanceOrder.device.classroom:id,classroom_code,name',
            ])
            ->get()
            ->sortByDesc(fn ($usage) => $usage->maintenanceResult->executed_at->timestamp)
            ->values();
    }

    /**
     * 把保養用料併入「備品使用彙總」：同一個零件的數量與金額加在一起。
     * 維修彙總是資料庫查出來的，這裡統一轉成簡單物件，畫面與匯出都用同樣的欄位。
     *
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $repairSummary
     * @param  Collection<int, MaintenancePartUsage>  $maintenanceUsages
     * @return Collection<int, \stdClass>
     */
    private function mergeMaintenanceIntoSummary(Collection $repairSummary, Collection $maintenanceUsages): Collection
    {
        /** @var array<int, \stdClass> $rows */
        $rows = [];
        foreach ($repairSummary as $item) {
            $row = new \stdClass;
            $row->part_id = $item->getAttribute('part_id');
            $row->part = $item->getRelation('part');
            $row->total_quantity = (int) $item->getAttribute('total_quantity');
            $row->known_subtotal = (int) $item->getAttribute('known_subtotal');
            $row->missing_price_count = (int) $item->getAttribute('missing_price_count');
            $rows[$item->getAttribute('part_id')] = $row;
        }

        foreach ($maintenanceUsages as $usage) {
            if (! isset($rows[$usage->part_id])) {
                $new = new \stdClass;
                $new->part_id = $usage->part_id;
                $new->part = $usage->part;
                $new->total_quantity = 0;
                $new->known_subtotal = 0;
                $new->missing_price_count = 0;
                $rows[$usage->part_id] = $new;
            }
            $row = $rows[$usage->part_id];
            $row->total_quantity += $usage->quantity;
            if ($usage->unit_price === null) {
                $row->missing_price_count++;
            } else {
                $row->known_subtotal += $usage->quantity * $usage->unit_price;
            }
        }

        return collect(array_values($rows));
    }

    /**
     * 期間內的進貨批次（依進貨日期），新的在前；包含新增零件時填的「期初庫存」（備註欄標示）。
     *
     * @return \Illuminate\Support\Collection<int, PartPurchase>
     */
    private function purchases(?string $startDate, ?string $endDate)
    {
        return PartPurchase::query()
            ->with('part:id,part_code,name,specification,unit')
            ->when($startDate, fn ($q) => $q->whereDate('purchase_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('purchase_date', '<=', $endDate))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->get();
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