<?php

namespace App\Http\Controllers;

use App\Exports\CostReportSelectedExport;
use App\Exports\RepairCostAnalysisExport;
use App\Support\CostReportSections;
use App\Services\RepairCostAnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

        // 「匯出 Excel／PDF」預覽視窗：各區塊的欄位與資料（跟匯出的內容相同）。
        $data['exportSections'] = CostReportSections::build($data);

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

        // 從預覽視窗送出：只匯出勾選的區塊與欄位；直接開網址（沒有 sections）時維持完整匯出。
        if ($request->has('sections')) {
            return Excel::download(new CostReportSelectedExport($this->selectedSections($request, $data)), $fileName);
        }

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

        // 中文字型 Noto Sans TC 收錄六萬多個字，dompdf 讀取字型對照表（cmap）時會用掉超過
        // PHP 預設的 128MB，所以只在產生 PDF 這一次把記憶體上限提高到 512MB。
        ini_set('memory_limit', '512M');

        // dompdf 會把字型對照表快取在 storage/fonts；這個資料夾不在版本控制裡（.gitignore），
        // 全新複製下來的專案裡沒有，沒建好會直接 500，所以匯出前先確保它存在。
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = $request->has('sections')
            ? Pdf::loadView('repair-cost-analysis.pdf-selected', $data + ['sections' => $this->selectedSections($request, $data)])
            : Pdf::loadView('repair-cost-analysis.pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        $fileName = 'repair-cost-analysis-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * 預覽視窗勾選的區塊（sections[]）與各區塊的欄位（cols[區塊][] = 欄位索引）。
     * 至少要勾一個區塊，而且勾選的區塊至少要有一個欄位。
     */
    private function selectedSections(Request $request, array $data): array
    {
        $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string', 'in:summary,parts,details,scraps'],
            'cols' => ['nullable', 'array'],
        ], [
            'sections.required' => '請至少勾選一個要匯出的區塊。',
        ]);

        $picked = CostReportSections::pick(
            CostReportSections::build($data),
            $request->input('sections', []),
            $request->input('cols', [])
        );

        if (! $picked) {
            throw ValidationException::withMessages(['cols' => '請至少勾選一個要匯出的欄位。']);
        }

        return $picked;
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
            // 報廢損失：include＝一起顯示（預設）、exclude＝不顯示。
            'scraps' => ['nullable', 'in:include,exclude'],
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