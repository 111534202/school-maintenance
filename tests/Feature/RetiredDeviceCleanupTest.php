<?php

namespace Tests\Feature;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use App\Models\RepairLog;
use App\Models\RepairRequest;
use App\Services\DeviceStatusService;
use App\Services\MaintenanceCompletionRateService;
use App\Services\RepairRequestWorkflow;
use App\Services\RetiredDeviceCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 設備淘汰的收尾（2026-11-21 使用者要求）：設備一旦「已淘汰」，名下所有進行中的東西都要中止並轉成唯讀。
 * 這裡逐項驗證：中止範圍、歷史紀錄不動、唯讀（畫面不給入口＋後端擋下）、其他設備不受影響。
 */
class RetiredDeviceCleanupTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code, string $status = 'normal'): Device
    {
        $room = Classroom::firstOrCreate(['classroom_code' => 'D101'], ['campus' => '本', 'building' => 'D', 'floor' => '1', 'name' => 'D101 教室']);

        return Device::create([
            'device_code' => $code, 'device_category_id' => DeviceCategory::firstOrCreate(['name' => '投影機'])->id,
            'classroom_id' => $room->id, 'status' => $status,
        ]);
    }

    private function repair(Device $device, string $status): RepairRequest
    {
        return RepairRequest::factory()->create(['device_id' => $device->id, 'status' => $status, 'title' => '報修-' . $status . '-' . $device->device_code]);
    }

    public function test_retiring_a_device_aborts_everything_in_progress_and_keeps_the_history(): void
    {
        $device = $this->device('DEV-A');
        $open = collect(['pending', 'assigned', 'in_progress', 'pending_review'])->map(fn ($s) => $this->repair($device, $s));
        $done = $this->repair($device, 'completed');
        $pendingOrder = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_PENDING]);
        $runningOrder = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_IN_PROGRESS]);
        $doneOrder = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);
        $plan = MaintenancePlan::factory()->create(['device_id' => $device->id, 'is_active' => true]);
        $candidate = PreventiveCandidate::create(['device_id' => $device->id, 'risk_score' => 0.8, 'threshold' => 0.5, 'algorithm' => 'rule_based', 'explanation' => [], 'features' => [], 'status' => PreventiveCandidate::STATUS_PENDING]);
        $ngOrder = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);
        $ng = MaintenanceResult::factory()->create(['maintenance_order_id' => $ngOrder->id, 'result' => 'ng', 'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_PENDING]);

        // 另一台正常設備名下的東西不受影響。
        $other = $this->device('DEV-B');
        $otherRepair = $this->repair($other, 'in_progress');
        $otherOrder = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $other->id, 'status' => MaintenanceOrder::STATUS_PENDING]);
        $otherPlan = MaintenancePlan::factory()->create(['device_id' => $other->id, 'is_active' => true]);

        DeviceStatusService::updateStatus($device, 'retired', '淘汰');

        foreach ($open as $repair) {
            $this->assertSame(RepairRequestStatus::Aborted, $repair->fresh()->status, '進行中的報修單要中止');
        }
        $this->assertSame(RepairRequestStatus::Completed, $done->fresh()->status, '已結案的歷史不動');
        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $pendingOrder->fresh()->status);
        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $runningOrder->fresh()->status);
        $this->assertSame(MaintenanceOrder::STATUS_COMPLETED, $doneOrder->fresh()->status, '已完成的工單不動');
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(PreventiveCandidate::STATUS_REJECTED, $candidate->fresh()->status);
        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $ng->fresh()->ng_conversion_status);

        $this->assertSame(RepairRequestStatus::InProgress, $otherRepair->fresh()->status);
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $otherOrder->fresh()->status);
        $this->assertTrue($otherPlan->fresh()->is_active);

        // 每一筆被中止的東西都留下操作紀錄。
        $this->assertDatabaseHas('audit_logs', ['loggable_type' => RepairRequest::class, 'loggable_id' => $open[0]->id, 'action' => 'status_changed']);
        $this->assertDatabaseHas('audit_logs', ['loggable_type' => MaintenanceOrder::class, 'loggable_id' => $pendingOrder->id, 'action' => 'status_changed']);
    }

    public function test_the_device_edit_form_retire_goes_through_the_cleanup(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-FORM');
        $repair = $this->repair($device, 'assigned');
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_PENDING]);

        $this->actingAs($admin)->get(route('devices.edit', $device))->assertOk()->assertSee('確認淘汰設備');
        $this->actingAs($admin)->put(route('devices.update', $device), [
            'device_code' => $device->device_code, 'device_category_id' => $device->device_category_id, 'classroom_id' => $device->classroom_id, 'status' => 'retired',
        ])->assertRedirect(route('devices.index'));

        $this->assertSame('retired', $device->fresh()->status);
        $this->assertSame(RepairRequestStatus::Aborted, $repair->fresh()->status);
        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $order->fresh()->status);
    }

    public function test_an_aborted_repair_is_read_only(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-R', 'retired');
        $repair = $this->repair($device, 'aborted');

        $this->actingAs($admin)->get(route('repairs.show', $repair))->assertOk()->assertSee('已中止')
            ->assertDontSee(route('repairs.edit', $repair), false)->assertDontSee(route('repairs.destroy', $repair), false);
        $this->actingAs($admin)->get(route('repairs.edit', $repair))->assertRedirect(route('repairs.show', $repair))->assertSessionHas('error');
        $this->actingAs($admin)->put(route('repairs.update', $repair), ['title' => '被改', 'description' => 'x', 'impact_level' => 'low', 'is_class_affected' => 0])
            ->assertRedirect(route('repairs.show', $repair))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('repairs.destroy', $repair))->assertSessionHas('error');
        $technician = $this->makeTechnician();
        $this->actingAs($admin)->post(route('repairs.assign', $repair), ['assigned_to' => $technician->id])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('repairs.reassign', $repair), ['assigned_to' => $technician->id])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('repairs.start', $repair))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('repair-logs.create', $repair))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($admin)->post(route('repair-logs.store', $repair), ['cause' => '不該寫入', 'resolution' => '不該寫入', 'started_at' => now()->subHour()->format('Y-m-d H:i'), 'ended_at' => now()->format('Y-m-d H:i')])->assertSessionHas('error');
        $this->assertSame(0, RepairLog::count());
        $this->actingAs($admin)->post(route('repairs.complete', $repair))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('repairs.reject', $repair), ['rejection_reason' => '不行'])->assertSessionHas('error');

        $fresh = $repair->fresh();
        $this->assertSame(RepairRequestStatus::Aborted, $fresh->status);
        $this->assertNotSame('被改', $fresh->title);
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_aborted_repairs_are_not_counted_as_open_anywhere(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-CNT');
        $this->repair($device, 'in_progress');
        DeviceStatusService::updateStatus($device, 'retired');

        // 進行中篩選、教室與設備列表的「進行中」數字都不含已中止。
        $this->actingAs($admin)->get(route('repairs.index', ['open' => 1]))->assertOk()->assertDontSee('報修-in_progress-DEV-CNT');
        $this->actingAs($admin)->get(route('repairs.index', ['status' => 'aborted']))->assertOk()->assertSee('報修-in_progress-DEV-CNT');
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('openCount', 0);
        $this->actingAs($admin)->get(route('devices.index'))->assertOk()->assertViewHas('devices', fn ($devices) => $devices->first()->open_repairs_count === 0);
    }

    public function test_an_aborted_maintenance_order_is_read_only(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-O', 'retired');
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_ABORTED, 'scheduled_date' => now()->addDays(3)->toDateString()]);
        $date = $order->scheduled_date->toDateString();

        $this->actingAs($admin)->get(route('maintenance-orders.show', $order))->assertOk()->assertSee('已中止')
            ->assertDontSee(route('maintenance-orders.edit', $order), false)->assertDontSee(route('maintenance-orders.destroy', $order), false)
            ->assertDontSee(route('maintenance-orders.results.create', $order), false)->assertDontSee(route('maintenance-orders.assign', $order), false);
        $this->actingAs($admin)->get(route('maintenance-orders.edit', $order))->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');
        $this->actingAs($admin)->put(route('maintenance-orders.update', $order), ['scheduled_date' => now()->addDays(30)->toDateString()])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('maintenance-orders.destroy', $order))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('maintenance-orders.start', $order))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $admin->id])->assertSessionHas('error');
        $this->actingAs($admin)->get(route('maintenance-orders.results.create', $order))->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => now()->subHour()->format('Y-m-d H:i')])->assertSessionHas('error');

        $fresh = $order->fresh();
        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $fresh->status);
        $this->assertSame($date, $fresh->scheduled_date->toDateString());
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->assigned_to);
        $this->assertSame(0, MaintenanceResult::count());
    }

    public function test_aborted_orders_are_not_unfinished_and_not_in_the_completion_rate(): void
    {
        $device = $this->device('DEV-RATE', 'retired');
        MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_ABORTED, 'scheduled_date' => now()->toDateString()]);
        MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_COMPLETED, 'scheduled_date' => now()->toDateString()]);

        $this->assertSame(0, MaintenanceOrder::unfinished()->count());
        $this->assertSame(0, MaintenanceOrder::overdue()->count());
        $rate = app(MaintenanceCompletionRateService::class)->forPeriod();
        $this->assertSame(1, $rate['total']);
        $this->assertSame(1, $rate['completed']);
    }

    public function test_plans_of_a_retired_device_are_read_only(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-P');
        $plan = MaintenancePlan::factory()->create(['device_id' => $device->id, 'is_active' => true, 'name' => '原計畫名']);
        DeviceStatusService::updateStatus($device, 'retired');
        $plan = $plan->fresh();
        $this->assertFalse($plan->is_active);
        $this->assertTrue($plan->isLocked());

        $this->actingAs($admin)->get(route('maintenance-plans.index'))->assertOk()
            ->assertSee('已停用（設備已淘汰）')->assertDontSee(route('maintenance-plans.edit', $plan), false)->assertDontSee(route('maintenance-plans.toggle-status', $plan), false);
        $this->actingAs($admin)->get(route('maintenance-plans.edit', $plan))->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('error');
        $this->actingAs($admin)->put(route('maintenance-plans.update', $plan), [
            'name' => '被改的名稱', 'cycle_days' => 10, 'start_date' => now()->toDateString(), 'item_ids' => [MaintenanceItem::factory()->create()->id],
        ])->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('maintenance-plans.toggle-status', $plan))->assertSessionHas('error');

        $fresh = $plan->fresh();
        $this->assertSame('原計畫名', $fresh->name);
        $this->assertFalse($fresh->is_active);
    }

    public function test_the_one_off_cleanup_also_covers_devices_that_were_already_retired(): void
    {
        // 模擬舊資料：設備早就是已淘汰，名下卻還有進行中的東西（直接寫資料庫，沒經過 updateStatus）。
        $device = $this->device('DEV-LEGACY', 'retired');
        $repair = $this->repair($device, 'assigned');
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => null, 'device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_PENDING]);

        $summary = app(RetiredDeviceCleanup::class)->run($device);

        $this->assertSame(['repairs' => 1, 'orders' => 1, 'plans' => 0, 'candidates' => 0, 'ng_results' => 0], $summary);
        $this->assertSame(RepairRequestStatus::Aborted, $repair->fresh()->status);
        $this->assertSame(MaintenanceOrder::STATUS_ABORTED, $order->fresh()->status);
        // 再跑一次什麼都不會變（可以重複執行）。
        $this->assertSame(['repairs' => 0, 'orders' => 0, 'plans' => 0, 'candidates' => 0, 'ng_results' => 0], app(RetiredDeviceCleanup::class)->run($device));
    }

    public function test_a_failure_during_cleanup_rolls_the_whole_retirement_back(): void
    {
        $device = $this->device('DEV-ROLL');
        $repair = $this->repair($device, 'assigned');

        $this->app->bind(RetiredDeviceCleanup::class, fn () => new class(app(RepairRequestWorkflow::class)) extends RetiredDeviceCleanup
        {
            public function run(Device $device): array
            {
                throw new \RuntimeException('模擬收尾失敗');
            }
        });

        try {
            DeviceStatusService::updateStatus($device, 'retired');
            $this->fail('應該丟出例外');
        } catch (\RuntimeException) {
        }

        $this->assertSame('normal', $device->fresh()->status, '收尾失敗時設備狀態要一起退回');
        $this->assertSame(RepairRequestStatus::Assigned, $repair->fresh()->status);
    }
}
