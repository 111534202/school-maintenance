<?php

namespace App\Http\Controllers;

use App\Exports\RepairCostAnalysisExport;
use App\Services\RepairCostAnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class RepairCostAnalysisController extends Controller
{
    public function __construct(
        private RepairCostAnalysisService $costAnalysisService
    ) {}

    /**
     * 第一版材料成本分析。
     *
     * 日期依據：repair_logs.created_at。
     * 成本依據：repair_part_usages 的單價快照。
     * 報修單範圍：沿用 RepairRequest::visibleTo()。
     */
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);

        $data = $this->costAnalysisService->generate(
            $request->user(),
            $filters
        );

        return view('repair-cost-analysis.index', $data);
    }

    /**
     * 匯出材料成本分析 Excel。
     */
    public function exportExcel(Request $request)
    {
        $filters = $this->validatedFilters($request);

        $data = $this->costAnalysisService->generate(
            $request->user(),
            $filters
        );

        $fileName = 'repair-cost-analysis-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(
            new RepairCostAnalysisExport($data),
            $fileName
        );
    }

    /**
     * 匯出繁體中文材料成本分析 PDF。
     */
    public function exportPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);

        $data = $this->costAnalysisService->generate(
            $request->user(),
            $filters
        );

        $pdf = Pdf::loadView('repair-cost-analysis.pdf', $data)
            ->setPaper('a4', 'landscape');

        $fileName = 'repair-cost-analysis-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * 網頁與 Excel 匯出共用的篩選驗證。
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => [
                'nullable',
                'in:pending,assigned,in_progress,pending_review,completed',
            ],
        ]);

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if (
            $startDate !== null
            && $endDate !== null
            && $startDate > $endDate
        ) {
            throw ValidationException::withMessages([
                'end_date' => '結束日期不可早於開始日期。',
            ]);
        }

        return $filters;
    }
}