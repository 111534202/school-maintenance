<?php

namespace App\Http\Controllers;

use App\Models\RepairPartUsage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RepairCostAnalysisController extends Controller
{
    /**
     * 第一版材料成本分析。
     *
     * 日期依據：repair_logs.created_at。
     * 成本依據：repair_part_usage 的單價快照。
     * 報修單範圍：沿用 RepairRequest::visibleTo()。
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw ValidationException::withMessages([
                'end_date' => '結束日期不可早於開始日期。',
            ]);
        }

        $user = $request->user();

        $partUsages = RepairPartUsage::query()
            ->with([
                'part:id,name',
                'repairLog:id,repair_request_id,created_at',
            ])
            ->whereHas('repairLog', function ($query) use ($startDate, $endDate, $user) {
                if ($startDate !== null) {
                    $query->whereDate('created_at', '>=', $startDate);
                }

                if ($endDate !== null) {
                    $query->whereDate('created_at', '<=', $endDate);
                }

                $query->whereHas('repairRequest', function ($repairRequests) use ($user) {
                    $repairRequests->visibleTo($user);
                });
            })
            ->orderByDesc(
    		\App\Models\RepairLog::query()
        		->select('created_at')
        		->whereColumn(
            		'repair_logs.id',
            		'repair_part_usage.repair_log_id'
        		)
	        )
            ->get();

        $knownSubtotal = $partUsages
            ->filter(fn ($usage) => $usage->unit_price !== null)
            ->sum(fn ($usage) => $usage->quantity * $usage->unit_price);

        $missingPriceCount = $partUsages
            ->filter(fn ($usage) => $usage->unit_price === null)
            ->count();

        $hasIncompleteCost = $missingPriceCount > 0;

        return view('repair-cost-analysis.index', [
            'partUsages' => $partUsages,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'knownSubtotal' => $knownSubtotal,
            'missingPriceCount' => $missingPriceCount,
            'hasIncompleteCost' => $hasIncompleteCost,
        ]);
    }
}
