<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Models\Part;
use Database\Factories\RepairRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 深度檢測補上的「時間先後」與「合理範圍」規則：
 * 保養執行時間不早於工單建立、預計處理時間不早於報修送出、日期欄位不能是離譜的年份、結束日期不能早於起始日期。
 */
class TimeOrderRulesTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
    }

    public function test_maintenance_result_cannot_be_executed_before_the_order_was_created(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $order = MaintenanceOrder::factory()->create(['created_at' => '2026-11-18 10:30:45']);

        // 比工單建立時間早 1 分鐘：被擋，而且不會留下保養結果。
        $this->actingAs($admin)
            ->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-11-18 10:29'])
            ->assertSessionHasErrors('executed_at');
        $this->assertStringContainsString('不能早於保養工單建立的時間', session('errors')->first('executed_at'));
        $this->assertFalse($order->fresh()->result()->exists());

        // 同一分鐘（輸入欄位只到分鐘）可以。
        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-11-18 10:30'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->result()->exists());
    }

    public function test_maintenance_result_form_limits_the_execution_time(): void
    {
        $order = MaintenanceOrder::factory()->create(['created_at' => '2026-11-18 10:30:45']);

        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))
            ->get(route('maintenance-orders.results.create', $order))
            ->assertOk()
            ->assertSee('min="2026-11-18T10:30"', false);
    }

    public function test_dispatch_time_cannot_be_before_the_repair_was_requested(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $technician = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'pending', 'created_at' => '2026-11-18 09:30:45']);

        $this->actingAs($admin)
            ->post(route('repairs.assign', $case), ['assigned_to' => $technician->id, 'scheduled_at' => '2026-11-17 09:00'])
            ->assertSessionHasErrors('scheduled_at');
        $this->assertSame('pending', $case->fresh()->status->value);

        // 沒填預計處理時間、或晚於報修送出時間都可以。
        $this->post(route('repairs.assign', $case), ['assigned_to' => $technician->id, 'scheduled_at' => '2026-11-19 09:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame('assigned', $case->fresh()->status->value);
    }

    // 維修的開始、結束時間都不能晚於「填寫送出的那一刻」（不再有 5 分鐘的寬限）；保養結果的執行時間同理。
    public function test_repair_and_maintenance_times_cannot_be_later_than_the_moment_of_submission(): void
    {
        $technician = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'in_progress', 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 09:00:00']);
        $base = ['cause' => '壞了', 'resolution' => '修好'];

        // 現在是 11:00：結束時間 11:01（只晚 1 分鐘）也不行；開始時間同理。
        $this->actingAs($technician)
            ->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-18 10:00', 'ended_at' => '2026-11-18 11:01'])
            ->assertSessionHasErrors('ended_at');
        $this->assertStringContainsString('不能晚於現在的時間', session('errors')->first('ended_at'));
        $this->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-18 11:01', 'ended_at' => '2026-11-18 11:01'])
            ->assertSessionHasErrors(['started_at', 'ended_at']);
        $this->assertSame(0, $case->fresh()->repairLogs()->count());

        // 剛好是現在這一分鐘可以。
        $this->post(route('repair-logs.store', $case), $base + ['started_at' => '2026-11-18 10:00', 'ended_at' => '2026-11-18 11:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $case->fresh()->repairLogs()->count());

        // 表單也限制最晚時間（現在這一分鐘），瀏覽器就選不到未來的時間。
        $another = RepairRequestFactory::new()->create(['status' => 'in_progress', 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 09:00:00']);
        $this->get(route('repair-logs.create', $another))->assertOk()->assertSee('max="2026-11-18T11:00"', false);

        // 保養結果的執行時間：晚 1 分鐘不行。
        $order = MaintenanceOrder::factory()->create(['created_at' => '2026-11-18 08:00:00']);
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))
            ->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-11-18 11:01'])
            ->assertSessionHasErrors('executed_at');
        $this->get(route('maintenance-orders.results.create', $order))->assertSee('max="2026-11-18T11:00"', false);
    }

    public function test_absurd_years_are_rejected_for_dates(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $part = Part::create(['name' => '測試零件', 'unit' => '個', 'current_stock' => 5, 'safety_stock' => 1]);
        $order = MaintenanceOrder::factory()->create();
        $this->actingAs($admin);

        $this->post(route('parts.purchases.store', $part), ['purchase_date' => '1000-01-01', 'vendor_name' => '甲', 'quantity' => 1, 'unit_price' => 1])
            ->assertSessionHasErrors('purchase_date');
        $this->post(route('parts.scraps.store', $part), ['scrap_date' => '1000-01-01', 'quantity' => 1, 'reason' => '損壞'])
            ->assertSessionHasErrors('scrap_date');
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2099-01-01'])
            ->assertSessionHasErrors('scheduled_date');
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '1990-01-01'])
            ->assertSessionHasErrors('scheduled_date');
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2026-12-01'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_end_date_before_the_start_date_is_rejected_on_filters(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->get(route('audit-logs.index', ['date_from' => '2026-11-10', 'date_to' => '2026-11-01']))->assertSessionHasErrors('date_to');
        $this->get(route('repairs.index', ['created_from' => '2026-11-10', 'created_to' => '2026-11-01']))->assertSessionHasErrors('created_to');
        $this->get(route('maintenance-orders.index', ['from' => '2026-11-10', 'to' => '2026-11-01']))->assertSessionHasErrors('to');

        // 同一天、或只填一邊都可以。
        $this->get(route('audit-logs.index', ['date_from' => '2026-11-10', 'date_to' => '2026-11-10']))->assertOk();
        $this->get(route('audit-logs.index', ['date_to' => '2026-11-01']))->assertOk();
        $this->get(route('repairs.index', ['created_from' => '2026-11-10']))->assertOk();
    }
}
