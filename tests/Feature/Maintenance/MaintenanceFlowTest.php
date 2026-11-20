<?php

namespace Tests\Feature\Maintenance;

use App\Enums\RepairRequestStatus;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use App\Services\RepairRequestWorkflow;
use App\Services\DueMaintenanceOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 保養主流程測試——計畫 → 工單 → 確認派工 → OK／NG 回報、NG → 每台設備一張報修單（依《報修與保養流程修改說明》）。
 */
class MaintenanceFlowTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->actingAs($this->admin = $this->makeUser('admin'));
    }

    private \App\Models\User $admin;

    /** 已確認派工（進行中、負責人是目前登入的管理員）的工單，可以直接回報。 */
    private function pendingOrder(string $status = MaintenanceOrder::STATUS_IN_PROGRESS): MaintenanceOrder
    {
        $device = $this->makeDevice();

        return MaintenanceOrder::create([
            'maintenance_plan_id' => $this->makePlan($device)->id,
            'device_id' => $device->id,
            'device_category_id' => $device->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => $status,
            'assigned_to' => $status === MaintenanceOrder::STATUS_PENDING ? null : $this->admin->id,
            'scheduled_date' => now()->toDateString(),
        ]);
    }

    /** 計畫是一整個設備類別的工單（已派工），類別裡有 $codes 這幾台設備。 */
    private function categoryOrder(array $codes): array
    {
        $devices = collect($codes)->map(fn ($code) => $this->makeDevice($code));
        $plan = MaintenancePlan::create([
            'name' => '整類月檢', 'description' => '清潔', 'device_category_id' => $devices[0]->device_category_id,
            'cycle_days' => 30, 'start_date' => now()->subMonth()->toDateString(), 'next_due_date' => now()->toDateString(), 'is_active' => true,
        ]);
        $order = MaintenanceOrder::create([
            'maintenance_plan_id' => $plan->id, 'device_category_id' => $plan->device_category_id, 'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_IN_PROGRESS, 'assigned_to' => $this->admin->id, 'scheduled_date' => now()->toDateString(),
        ]);

        return [$order, $devices];
    }

    // ---- 計畫 → 工單 ----

    public function test_creating_an_order_from_a_plan_copies_device_and_due_date(): void
    {
        $device = $this->makeDevice();
        $plan = $this->makePlan($device, ['next_due_date' => '2026-10-20']);

        $this->post(route('maintenance-plans.create-order', $plan))
            ->assertRedirect(route('maintenance-orders.index'));

        $order = MaintenanceOrder::firstOrFail();
        $this->assertSame($plan->id, $order->maintenance_plan_id);
        $this->assertSame($device->id, $order->device_id);
        $this->assertSame(MaintenanceOrder::SOURCE_PERIODIC, $order->source);
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->status);
        $this->assertSame('2026-10-20', $order->scheduled_date->toDateString());
    }

    public function test_inactive_plan_cannot_create_an_order(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['is_active' => false]);

        $this->post(route('maintenance-plans.create-order', $plan))->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('error');

        $this->assertSame(0, MaintenanceOrder::count());
    }

    // 工單提早一個週期產生：下次到期日是昨天（那一期早該產生）→ 補產生昨天那一期，而且昨天就到了下一期的產生日，
    // 所以下一期（昨天＋30 天）也一起產生；重跑不會疊加。
    public function test_due_order_generator_creates_one_order_per_due_date_only(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['next_due_date' => now()->subDay()->toDateString()]);
        $generator = app(DueMaintenanceOrderGenerator::class);

        $this->assertSame(2, $generator->generate());
        $this->assertSame(0, $generator->generate()); // 重跑不會疊加

        $this->assertSame(
            [now()->subDay()->toDateString(), now()->addDays(29)->toDateString()],
            MaintenanceOrder::where('maintenance_plan_id', $plan->id)->orderBy('scheduled_date')->get()->map(fn ($o) => $o->scheduled_date->toDateString())->all()
        );
        $this->assertSame(now()->addDays(59)->toDateString(), $plan->fresh()->next_due_date->toDateString());
    }

    // 劉家芸確認的例子：11/01 新增計畫，起始日 11/01、週期 10 天 → 存檔馬上產生排定 11/11 的工單，下次到期日 11/21；
    // 11/10 之前都不會再產生；到了 11/11 產生排定 11/21 的工單，下次到期日 12/01。工單有沒有做完都不影響。
    public function test_orders_are_generated_one_cycle_ahead(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-01 09:00'));
        $device = $this->makeDevice();
        $this->post(route('maintenance-plans.store'), [
            'name' => '十天一次', 'targets' => ['device:' . $device->id], 'description' => '清潔', 'cycle_days' => 10, 'start_date' => '2026-11-01', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '排定 2026-11-11'));

        $plan = \App\Models\MaintenancePlan::where('name', '十天一次')->sole();
        $dates = fn () => MaintenanceOrder::where('maintenance_plan_id', $plan->id)->orderBy('scheduled_date')->get()->map(fn ($o) => $o->scheduled_date->toDateString())->all();
        $this->assertSame(['2026-11-11'], $dates());
        $this->assertSame('2026-11-21', $plan->fresh()->next_due_date->toDateString());

        $generator = app(DueMaintenanceOrderGenerator::class);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 00:00'));
        $this->assertSame(0, $generator->generate());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-11 00:00'));
        $this->assertSame(1, $generator->generate());
        $this->assertSame(['2026-11-11', '2026-11-21'], $dates());
        $this->assertSame('2026-12-01', $plan->fresh()->next_due_date->toDateString());

        // 第一張到了 11/12 還沒做完：逾期，但下一張照常（不會因為沒做完而停住）。
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 09:00'));
        $this->assertTrue(MaintenanceOrder::where('maintenance_plan_id', $plan->id)->orderBy('scheduled_date')->first()->isOverdue());
    }

    // ---- OK / NG 回報 ----

    public function test_reporting_ok_completes_the_order(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ok',
            'executed_at' => '2026-10-07 08:30:00',
        ])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);

        $result = MaintenanceResult::firstOrFail();
        $this->assertSame(MaintenanceResult::RESULT_OK, $result->result);
        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $result->ng_conversion_status);
    }

    // 確認派工：待處理 → 進行中；沒派工的工單不能回報，也沒有「回報結果」按鈕。
    public function test_dispatching_moves_the_order_to_in_progress_and_only_then_it_can_be_reported(): void
    {
        $order = $this->pendingOrder(MaintenanceOrder::STATUS_PENDING);
        $technician = $this->makeUser('technician');

        $this->get(route('maintenance-orders.index'))->assertSee('確認派工')->assertDontSee(route('maintenance-orders.results.create', $order), false);
        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-10-07 08:30:00'])
            ->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');
        $this->assertSame(0, MaintenanceResult::count());

        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => ''])->assertSessionHasErrors('assigned_to');
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->fresh()->status);

        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $technician->id])->assertSessionHas('success');
        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertSame($technician->id, $order->fresh()->assigned_to);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('maintenance-orders.start'), '開始保養已拿掉');

        $this->actingAs($technician)->get(route('maintenance-orders.index'))->assertSee(route('maintenance-orders.results.create', $order), false);
    }

    // NG（計畫是一台設備）：那台設備建一張報修單、帶入說明，工單「已轉報修」；報修單結案後工單才「已完成」。
    public function test_reporting_ng_converts_the_order_and_completes_it_when_the_repair_is_closed(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ng', 'executed_at' => '2026-10-07 08:30:00', 'note' => '風扇異音', 'ng_device_ids' => [$order->device_id],
        ])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame(MaintenanceOrder::STATUS_CONVERTED, $order->fresh()->status);
        $this->assertSame(['已轉報修', 'info'], $order->fresh()->statusLabel());
        $result = MaintenanceResult::firstOrFail();
        $this->assertSame('風扇異音', $result->note);
        $repair = RepairRequest::where('maintenance_result_id', $result->id)->sole();
        $this->assertSame($order->device_id, $repair->device_id);
        $this->assertSame(RepairRequestStatus::Pending, $repair->status);
        $this->assertStringContainsString('說明：風扇異音', $repair->description);
        $this->assertSame($this->admin->id, $repair->requester_id);

        $repair->update(['status' => RepairRequestStatus::PendingReview->value]);
        app(RepairRequestWorkflow::class)->complete($repair);
        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    // NG（計畫是設備類別）：從這一類的設備複選，每台各一張報修單；全部結束（已退回也算）才完成。
    public function test_category_ng_creates_one_repair_per_selected_device(): void
    {
        [$order, $devices] = $this->categoryOrder(['CAT-1', 'CAT-2', 'CAT-3']);

        $this->get(route('maintenance-orders.results.create', $order))->assertOk()->assertSee('CAT-1')->assertSee('CAT-3');
        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'ng', 'executed_at' => '2026-10-07 08:30:00', 'note' => '濾網破損', 'ng_device_ids' => [$devices[0]->id, $devices[2]->id],
        ])->assertSessionHasNoErrors();

        $repairs = RepairRequest::orderBy('id')->get();
        $this->assertSame([$devices[0]->id, $devices[2]->id], $repairs->pluck('device_id')->all());
        $this->assertSame(MaintenanceOrder::STATUS_CONVERTED, $order->fresh()->status);

        $workflow = app(RepairRequestWorkflow::class);
        $workflow->returnRequest($repairs[0], '不是故障', $this->admin->id);
        $this->assertSame(MaintenanceOrder::STATUS_CONVERTED, $order->fresh()->status, '還有一張沒結案');
        $repairs[1]->update(['status' => RepairRequestStatus::PendingReview->value]);
        $workflow->complete($repairs[1]->fresh());
        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    // NG 一定要選設備、填說明；選了不在這張工單範圍的設備會被擋下，什麼都不寫入。OK 不用選設備。
    public function test_ng_needs_devices_from_the_order_and_a_note(): void
    {
        [$order, $devices] = $this->categoryOrder(['CAT-1']);
        $other = Device::create(['device_code' => 'OTHER', 'device_category_id' => \App\Models\DeviceCategory::create(['name' => '別類'])->id, 'classroom_id' => $devices[0]->classroom_id, 'status' => 'normal']);
        $base = ['result' => 'ng', 'executed_at' => '2026-10-07 08:30:00'];

        $this->post(route('maintenance-orders.results.store', $order), $base)->assertSessionHasErrors(['note', 'ng_device_ids']);
        $this->post(route('maintenance-orders.results.store', $order), $base + ['note' => 'x', 'ng_device_ids' => [$other->id]])->assertSessionHasErrors('ng_device_ids');

        $this->assertSame(0, MaintenanceResult::count());
        $this->assertSame(0, RepairRequest::count());
        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-10-07 08:30:00', 'ng_device_ids' => [$other->id]])->assertSessionHasNoErrors();
        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(0, RepairRequest::count());
    }

    public function test_result_requires_a_valid_result_value(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('maintenance-orders.results.store', $order), [
            'result' => 'maybe',
            'executed_at' => '2026-10-07 08:30:00',
        ])->assertSessionHasErrors('result');

        $this->assertSame(0, MaintenanceResult::count());
        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_an_order_cannot_be_reported_twice(): void
    {
        $order = $this->pendingOrder();
        $payload = ['result' => 'ok', 'executed_at' => '2026-10-07 08:30:00'];

        $this->post(route('maintenance-orders.results.store', $order), $payload);
        $this->post(route('maintenance-orders.results.store', $order), $payload)->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');

        $this->assertSame(1, MaintenanceResult::count());
    }
}
