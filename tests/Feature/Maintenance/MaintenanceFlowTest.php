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

    // 到了下次到期日（昨天）就產生排定在那天的工單；不改下次到期日；重跑不會疊加。
    public function test_due_order_generator_creates_one_order_per_due_date_only(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['next_due_date' => now()->subDay()->toDateString()]);
        $generator = app(DueMaintenanceOrderGenerator::class);

        $this->assertSame(1, $generator->generate());
        $this->assertSame(0, $generator->generate()); // 重跑不會疊加

        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());
        $this->assertSame(now()->subDay()->toDateString(), $plan->fresh()->next_due_date->toDateString());
    }

    // 劉家芸確認：到期日前 3 天產生排定在到期日的工單；工單做完後下次到期日才往後推一個週期，再到新的到期日前 3 天產生下一張。
    public function test_orders_are_generated_three_days_before_the_due_date_and_the_next_one_after_finishing(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-01 09:00'));
        $device = $this->makeDevice();
        $this->post(route('maintenance-plans.store'), [
            'name' => '十天一次', 'targets' => ['device:' . $device->id], 'description' => '清潔', 'cycle_days' => 10, 'start_date' => '2026-11-01', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', '保養計畫新增成功');   // 11/11 還在 3 天以外，先不產生

        $plan = \App\Models\MaintenancePlan::where('name', '十天一次')->sole();
        $this->assertSame('2026-11-11', $plan->next_due_date->toDateString());
        $generator = app(DueMaintenanceOrderGenerator::class);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-07 00:00'));
        $this->assertSame(0, $generator->generate());   // 離到期還有 4 天

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 09:00'));
        $this->assertSame(1, $generator->generate());   // 到期日前 3 天
        $order = MaintenanceOrder::where('maintenance_plan_id', $plan->id)->sole();
        $this->assertSame('2026-11-11', $order->scheduled_date->toDateString());
        $this->assertFalse($order->isOverdue());
        $this->assertSame('2026-11-11', $plan->fresh()->next_due_date->toDateString(), '還沒做完，下次到期日不動');

        // 11/09 提前做完 → 下次到期日推到 11/21；到了 11/18（前 3 天）才產生下一張。
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 10:00'));
        $order->update(['status' => MaintenanceOrder::STATUS_IN_PROGRESS, 'assigned_to' => $this->admin->id]);
        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-11-09 09:00:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-21', $plan->fresh()->next_due_date->toDateString());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-17 00:00'));
        $this->assertSame(0, $generator->generate());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 00:00'));
        $this->assertSame(1, $generator->generate());
    }

    // 新增／修改計畫時，下次到期日已經在 3 天內：存檔就馬上產生工單（排定在到期日），不用等半夜排程。
    public function test_saving_a_plan_due_within_three_days_creates_the_order_right_away(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-22 16:00'));
        $device = $this->makeDevice();
        $this->post(route('maintenance-plans.store'), [
            'name' => '三天內到期', 'targets' => ['device:' . $device->id], 'description' => '清潔', 'cycle_days' => 5, 'start_date' => '2026-11-20', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', '保養計畫新增成功；已產生保養工單 MO-00001（排定 2026-11-25）');

        $plan = \App\Models\MaintenancePlan::where('name', '三天內到期')->sole();
        $this->assertSame('2026-11-25', $plan->next_due_date->toDateString());
        $this->assertSame(['2026-11-25'], MaintenanceOrder::where('maintenance_plan_id', $plan->id)->get()->map(fn ($o) => $o->scheduled_date->toDateString())->all());

        // 停用的計畫存檔不產生；重新啟用時才產生。
        $this->post(route('maintenance-plans.store'), [
            'name' => '停用的', 'targets' => ['device:' . $device->id], 'description' => '清潔', 'cycle_days' => 5, 'start_date' => '2026-11-20', 'is_active' => 0,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', '保養計畫新增成功');
        $off = \App\Models\MaintenancePlan::where('name', '停用的')->sole();
        $this->assertSame(0, MaintenanceOrder::where('maintenance_plan_id', $off->id)->count());
        $this->patch(route('maintenance-plans.toggle-status', $off))->assertSessionHas('success', '已重新啟用；已產生保養工單 MO-00002（排定 2026-11-25）');
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

    // 工單是整類設備：NG 要勾出這一類裡異常的設備（只列還在使用的），每台各建一張報修單；全部結案後工單才完成。
    public function test_category_ng_asks_which_devices_and_creates_one_repair_each(): void
    {
        [$order, $devices] = $this->categoryOrder(['CAT-1', 'CAT-2', 'CAT-3']);
        $devices[2]->update(['status' => 'retired']);

        $this->get(route('maintenance-orders.results.create', $order))->assertOk()
            ->assertSee('name="ng_device_ids[]"', false)->assertSee('CAT-1')->assertDontSee('CAT-3');
        $base = ['result' => 'ng', 'executed_at' => '2026-10-07 08:30:00', 'note' => '濾網破損'];

        $this->post(route('maintenance-orders.results.store', $order), $base)->assertSessionHasErrors('ng_device_ids');   // 沒勾設備
        $this->post(route('maintenance-orders.results.store', $order), $base + ['ng_device_ids' => [$devices[2]->id]])->assertSessionHasErrors('ng_device_ids');   // 淘汰的不能選
        $this->assertSame(0, RepairRequest::count());

        $this->post(route('maintenance-orders.results.store', $order), $base + ['ng_device_ids' => [$devices[0]->id, $devices[1]->id]])->assertSessionHasNoErrors();
        $repairs = RepairRequest::orderBy('id')->get();
        $this->assertSame([$devices[0]->id, $devices[1]->id], $repairs->pluck('device_id')->all());
        $this->assertSame(MaintenanceOrder::STATUS_CONVERTED, $order->fresh()->status);

        foreach ($repairs as $i => $repair) {
            $repair->update(['status' => RepairRequestStatus::PendingReview->value]);
            app(RepairRequestWorkflow::class)->complete($repair->fresh());
            $this->assertSame($i === 0 ? MaintenanceOrder::STATUS_CONVERTED : MaintenanceOrder::STATUS_COMPLETED, $order->fresh()->status);
        }

        // 報修單的描述：「保養工單 MO-xxxxx」是連結，點了在對話框打開那張保養工單。
        $this->get(route('repairs.show', $repairs[0]))->assertOk()
            ->assertSee('<a href="' . route('maintenance-orders.show', $order) . '" data-modal-frame data-modal-title="保養紀錄" data-modal-size="xl">保養工單 ' . $order->displayNumber() . '</a>', false);
    }

    // NG 一定要填說明；沒填就整筆不寫入。OK 不用說明、不轉報修。
    public function test_ng_needs_a_note(): void
    {
        [$order] = $this->categoryOrder(['CAT-1']);
        $base = ['result' => 'ng', 'executed_at' => '2026-10-07 08:30:00'];

        $this->post(route('maintenance-orders.results.store', $order), $base)->assertSessionHasErrors(['note']);
        $this->assertSame(0, MaintenanceResult::count());
        $this->assertSame(0, RepairRequest::count());
        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2026-10-07 08:30:00'])->assertSessionHasNoErrors();
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
