<?php

namespace Tests\Feature;

use App\Exports\RepairCostAnalysisExport;
use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\RepairCostAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RepairCostAnalysisExportTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makeUsage(
        User $reporter,
        string $createdAt,
        string $partName,
        int $quantity,
        ?int $unitPrice
    ): RepairPartUsage {
        $repairRequest = RepairRequest::factory()->create([
            'requester_id' => $reporter->id,
            'assigned_to' => null,
        ]);

        $repairLog = RepairLog::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $part = Part::create([
            'name' => $partName,
            'specification' => '匯出測試規格',
            'unit_price' => 999,
            'current_stock' => 10,
            'safety_stock' => 1,
        ]);

        return RepairPartUsage::create([
            'repair_log_id' => $repairLog->id,
            'part_id' => $part->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);
    }

    public function test_export_contains_three_sheets_and_preserves_cost_rules(): void
    {
        $user = $this->makeUserWithPermissions(['maintenance.view']);

        $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '有歷史單價備品',
            2,
            150
        );

        $this->makeUsage(
            $user,
            '2026-10-10 10:00:00',
            '缺少歷史單價備品',
            3,
            null
        );

        $data = app(RepairCostAnalysisService::class)->generate($user, []);

        $export = new RepairCostAnalysisExport($data);
        $sheets = $export->sheets();

        $this->assertCount(3, $sheets);

        $this->assertSame(
            ['成本總覽', '備品彙總', '用料明細'],
            array_map(
                fn ($sheet) => $sheet->title(),
                $sheets
            )
        );

        foreach ($sheets as $sheet) {
            $this->assertInstanceOf(FromArray::class, $sheet);
            $this->assertInstanceOf(WithHeadings::class, $sheet);
            $this->assertInstanceOf(WithTitle::class, $sheet);
        }

        $summaryRows = $sheets[0]->array();

        $this->assertSame(
            ['已知材料費小計', 300.0, '只計算有歷史單價的用料明細'],
            $summaryRows[0]
        );
        $this->assertSame(1, $summaryRows[1][1]);
        $this->assertSame(2, $summaryRows[2][1]);

        $partsRows = $sheets[1]->array();

        $this->assertCount(2, $partsRows);

        $partsByName = collect($partsRows)->keyBy(fn ($row) => $row[0]);

        $this->assertSame(
            [2, 300.0, 0],
            array_slice($partsByName['有歷史單價備品'], 1)
        );

        $this->assertSame(
            [3, 0.0, 1],
            array_slice($partsByName['缺少歷史單價備品'], 1)
        );

        $detailsRows = $sheets[2]->array();

        $this->assertCount(2, $detailsRows);

        $detailsByName = collect($detailsRows)->keyBy(fn ($row) => $row[7]);

        $this->assertSame(150.0, $detailsByName['有歷史單價備品'][9]);
        $this->assertSame(300.0, $detailsByName['有歷史單價備品'][10]);

        $this->assertSame('缺少單價', $detailsByName['缺少歷史單價備品'][9]);
        $this->assertSame('缺少單價', $detailsByName['缺少歷史單價備品'][10]);
    }

    public function test_export_sheets_include_expected_headings(): void
    {
        $data = [
            'knownSubtotal' => 0,
            'missingPriceCount' => 0,
            'repairRequestCount' => 0,
            'startDate' => null,
            'endDate' => null,
            'status' => null,
            'partUsageSummary' => collect(),
            'partUsages' => collect(),
        ];

        $sheets = (new RepairCostAnalysisExport($data))->sheets();

        $this->assertSame(
            ['統計項目', '數值', '說明'],
            $sheets[0]->headings()
        );

        $this->assertSame(
            ['備品名稱', '使用總數量', '已知材料費小計', '缺少單價明細數'],
            $sheets[1]->headings()
        );

        $this->assertSame(
            [
                '維修日期',
                '案件編號',
                '維修紀錄編號',
                '維修案件',
                '案件狀態',
                '設備',
                '教室／地點',
                '備品名稱',
                '數量',
                '歷史單價',
                '材料費小計',
            ],
            $sheets[2]->headings()
        );
    }
    public function test_excel_export_downloads_with_date_and_status_filters(): void
    {
        Excel::fake();

        $user = $this->makeUserWithPermissions(['maintenance.view']);

        $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            '匯出篩選測試備品',
            2,
            150
        );

        $response = $this->actingAs($user)->get(
            route('repair-cost-analysis.export.excel', [
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'status' => 'pending',
            ])
        );

        $response->assertOk();

        Excel::matchByRegex();

        Excel::assertDownloaded(
            '/^repair-cost-analysis-.*\.xlsx$/',
            function ($export): bool {
                if (! $export instanceof RepairCostAnalysisExport) {
                    return false;
                }

                $sheets = $export->sheets();

                $summaryRows = $sheets[0]->array();
                $detailRows = $sheets[2]->array();

                return $summaryRows[3][1] === '2026-10-10'
                    && $summaryRows[4][1] === '2026-10-10'
                    && $summaryRows[5][1] === 'pending'
                    && count($detailRows) === 1
                    && $detailRows[0][7] === '匯出篩選測試備品';
            }
        );
    }

    public function test_excel_export_is_forbidden_without_permission(): void
    {
        Excel::fake();

        $user = $this->makeUserWithPermissions([]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.export.excel'))
            ->assertForbidden();
    }

    public function test_pdf_export_downloads_with_embedded_traditional_chinese_font(): void
    {
        $user = $this->makeUserWithPermissions(['maintenance.view']);

        $this->makeUsage(
            $user,
            '2026-10-10 09:00:00',
            'PDF 匯出測試備品',
            2,
            150
        );

        $response = $this->actingAs($user)->get(
            route('repair-cost-analysis.export.pdf', [
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'status' => 'pending',
            ])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $contentDisposition = $response->headers->get('content-disposition');

        $this->assertIsString($contentDisposition);
        $this->assertStringContainsString('repair-cost-analysis-', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('/FontFile2', $content);
        $this->assertStringContainsString('/ToUnicode', $content);
        $this->assertStringContainsString('NotoSansTC', $content);
    }

    public function test_pdf_export_is_forbidden_without_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->actingAs($user)
            ->get(route('repair-cost-analysis.export.pdf'))
            ->assertForbidden();
    }
}