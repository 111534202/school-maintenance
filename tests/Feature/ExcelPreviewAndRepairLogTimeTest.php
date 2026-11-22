<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 1. 維修紀錄的開始時間不能早於報修申請的時間。
 * 2. 列表頁的「匯出 Excel」要先預覽，確認後才下載（預覽不寫操作紀錄）。
 */
class ExcelPreviewAndRepairLogTimeTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_repair_log_cannot_start_before_the_repair_was_requested(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:30:45'));
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 09:30:45']);
        $base = ['cause' => '壞了', 'resolution' => '修好'];

        // 比報修送出時間早 1 分鐘：被擋，而且不會留下維修紀錄。
        $this->actingAs($technician)
            ->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-18 09:29', 'ended_at' => '2026-11-18 10:00'])
            ->assertSessionHasErrors('started_at');
        $this->assertSame(0, $case->fresh()->repairLogs()->count());
        $this->assertStringContainsString('不能早於報修申請的時間', session('errors')->first('started_at'));

        // 前一天更不行。
        $this->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-17 09:00', 'ended_at' => '2026-11-18 10:00'])
            ->assertSessionHasErrors('started_at');

        // 同一分鐘（輸入欄位只到分鐘）可以。
        $this->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-18 09:30', 'ended_at' => '2026-11-18 10:00'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('repairs.show', $case));
        $this->assertSame(1, $case->fresh()->repairLogs()->count());
    }

    public function test_repair_log_form_limits_the_start_time_to_after_the_request(): void
    {
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 09:30:45']);

        $this->actingAs($technician)->get(route('repair-logs.create', $case))
            ->assertOk()
            ->assertSee('min="2026-11-18T09:30"', false);
    }

    /** @return array<string, array{0: string}> */
    public static function excelRoutes(): array
    {
        return [
            '設備清單' => ['devices.export'],
            '用戶名單' => ['users.export'],
            '報修單' => ['repairs.export'],
            '保養工單' => ['maintenance-orders.export'],
            '操作紀錄' => ['audit-logs.export'],
        ];
    }

    #[DataProvider('excelRoutes')]
    public function test_excel_export_previews_first_and_only_logs_when_downloaded(string $routeName): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        // 預覽：回傳表格資料，不下載、不寫「匯出」操作紀錄。
        $this->getJson(route($routeName, ['preview' => 1]))
            ->assertOk()
            ->assertJsonStructure(['file', 'headings', 'rows', 'total', 'limit']);
        $this->assertSame(0, AuditLog::where('action', 'downloaded')->count());

        // 真的下載（沒有 preview）：才有檔案與操作紀錄。
        $response = $this->get(route($routeName));
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertSame(1, AuditLog::where('action', 'downloaded')->count());
    }

    public function test_list_pages_open_the_preview_instead_of_downloading_directly(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        foreach (['devices.index', 'users.index', 'repairs.index', 'maintenance-orders.index', 'audit-logs.index'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-excel-preview', false)
                ->assertSee('excelPreviewModal', false)
                ->assertSee('下載 Excel');
        }
    }
}
