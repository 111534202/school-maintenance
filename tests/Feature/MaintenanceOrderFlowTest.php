<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 保養工單流程：逾期標示、7 天內到期通知、確認派工（只有負責人與管理者能回報）、
 * 回報時可上傳附件、NG 顯示「已轉報修」、做完或取消後計畫的下次到期日往後推一個週期。
 */
class MaintenanceOrderFlowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function manager(): User
    {
        return $this->makeUserWithPermissions(['maintenance.manage', 'maintenance.view', 'maintenance.report']);
    }

    private function reporter(): User
    {
        return $this->makeUserWithPermissions(['maintenance.view', 'maintenance.report']);
    }

    private function report(MaintenanceOrder $order, array $extra = [])
    {
        return $this->post(route('maintenance-orders.results.store', $order), array_merge([
            'result' => 'ok', 'executed_at' => now()->format('Y-m-d\TH:i'),
        ], $extra));
    }

    /** 已確認派工給這位用戶的工單（進行中），他可以直接回報。 */
    private function dispatchedTo(User $user, array $attributes = []): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create(array_merge(['status' => 'in_progress', 'assigned_to' => $user->id], $attributes));
    }

    // ---------- 逾期 ----------

    public function test_unfinished_orders_past_their_date_are_marked_overdue_and_can_be_filtered(): void
    {
        $late = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->subDays(3)]);
        $onTime = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->addDays(3)]);
        $doneLate = MaintenanceOrder::factory()->create(['status' => 'completed', 'scheduled_date' => now()->subDays(10)]);

        $this->assertTrue($late->isOverdue());
        $this->assertFalse($onTime->isOverdue());
        $this->assertFalse($doneLate->isOverdue());   // 做完的不算逾期

        $this->actingAs($this->reporter());
        $this->get(route('maintenance-orders.index'))->assertOk()->assertSee('逾期 3 天');

        $ids = fn (array $query) => collect($this->get(route('maintenance-orders.index', $query))->viewData('orders')->items())->pluck('id')->all();
        $this->assertSame([$late->id], $ids(['due' => 'overdue']));
        $this->assertEqualsCanonicalizing([$late->id, $onTime->id], $ids(['due' => 'week']) + [1 => $onTime->id]);   // 7 天內到期含逾期
        $this->assertNotContains($onTime->id, $ids(['due' => 'overdue']));

        $this->get(route('maintenance-orders.show', $late))->assertSee('逾期 3 天');
    }

    // ---------- 通知鈴鐺 ----------

    public function test_the_bell_counts_orders_due_within_a_week_for_the_assignee_and_everyone_for_the_manager(): void
    {
        $reporter = $this->reporter();
        $other = $this->reporter();
        MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->addDays(2), 'assigned_to' => $reporter->id]);
        MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->subDays(1), 'assigned_to' => $reporter->id]);
        MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->addDays(20), 'assigned_to' => $reporter->id]);   // 還很久，不算
        MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->addDays(3), 'assigned_to' => null]);

        $this->actingAs($reporter)->get(route('dashboard'))->assertSee('2 張保養工單 7 天內到期（其中 1 張已逾期）');
        $this->actingAs($other)->get(route('dashboard'))->assertDontSee('張保養工單 7 天內到期');   // 不是他負責的
        $this->actingAs($this->manager())->get(route('dashboard'))
            ->assertSee('3 張保養工單 7 天內到期')                      // 管理者看全部
            ->assertSee('張保養工單還沒指定負責人');
    }

    // ---------- 指派負責人 ----------

    public function test_a_manager_can_assign_an_order_to_someone_who_can_report_and_it_is_logged(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $reporter = $this->reporter();

        $this->actingAs($this->manager())->get(route('maintenance-orders.show', $order))->assertSee('確認派工');
        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $reporter->id])->assertSessionHas('success');

        $this->assertSame($reporter->id, $order->fresh()->assigned_to);
        $this->assertSame('in_progress', $order->fresh()->status);   // 確認派工＝進行中
        $this->assertDatabaseHas('audit_logs', ['action' => 'assigned', 'loggable_id' => $order->id]);
        $this->get(route('maintenance-orders.show', $order))->assertSee($reporter->name)->assertSee('改派')->assertDontSee('</i>確認派工</button>', false);

        // 進行中可以改派，但不能取消派工（負責人必填）。
        $another = $this->reporter();
        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $another->id])->assertSessionHas('success');
        $this->assertSame($another->id, $order->fresh()->assigned_to);
        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => ''])->assertSessionHasErrors('assigned_to');
        $this->assertSame($another->id, $order->fresh()->assigned_to);
    }

    public function test_only_people_who_can_report_can_be_assigned_and_only_managers_can_assign(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $viewerOnly = $this->makeUserWithPermissions(['maintenance.view']);

        $this->actingAs($this->manager())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $viewerOnly->id])->assertSessionHasErrors('assigned_to');
        $this->assertNull($order->fresh()->assigned_to);

        $this->actingAs($this->reporter())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $this->reporter()->id])->assertForbidden();
    }

    public function test_a_completed_or_converted_order_cannot_be_assigned(): void
    {
        foreach (['completed', 'converted'] as $status) {
            $order = MaintenanceOrder::factory()->create(['status' => $status]);

            $this->actingAs($this->manager())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $this->reporter()->id])->assertSessionHas('error');
            $this->assertNull($order->fresh()->assigned_to);
        }
    }

    public function test_an_assigned_order_can_only_be_reported_by_the_assignee_or_a_manager(): void
    {
        $assignee = $this->reporter();
        $stranger = $this->reporter();
        $order = $this->dispatchedTo($assignee);

        $this->actingAs($stranger)->get(route('maintenance-orders.results.create', $order))->assertForbidden();
        $this->report($order)->assertForbidden();
        $this->assertFalse($order->fresh()->result()->exists());
        // 看板上也不給陌生人「回報保養結果」按鈕
        $this->get(route('maintenance-orders.show', $order))->assertDontSee(route('maintenance-orders.results.create', $order), false);

        $this->actingAs($assignee)->get(route('maintenance-orders.results.create', $order))->assertOk();
        $this->actingAs($this->manager())->get(route('maintenance-orders.results.create', $order))->assertOk();
        $this->actingAs($assignee);
        $this->report($order)->assertRedirect();
        $this->assertTrue($order->fresh()->result()->exists());
    }

    // 還沒確認派工（待處理）的工單：誰都不能回報，要先由保養管理者確認派工。
    public function test_an_order_that_is_not_dispatched_yet_cannot_be_reported(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending', 'assigned_to' => null]);

        $this->actingAs($this->manager());
        $this->get(route('maintenance-orders.results.create', $order))->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');
        $this->report($order)->assertSessionHas('error');
        $this->assertFalse($order->fresh()->result()->exists());
    }

    // ---------- 附件 ----------

    public function test_a_result_can_have_attachments_that_are_shown_and_downloadable_with_view_permission(): void
    {
        $this->actingAs($reporter = $this->reporter());
        $order = $this->dispatchedTo($reporter);

        $this->report($order, ['attachments' => [UploadedFile::fake()->image('before.jpg'), UploadedFile::fake()->image('after.png')]])->assertRedirect();

        $result = $order->fresh()->result;
        $this->assertCount(2, $result->attachments);
        $this->get(route('maintenance-orders.results.show', $order))->assertOk()->assertSee('before.jpg')->assertSee('after.png')->assertDontSee('共用附件機制尚未建立');

        $file = $result->attachments->first();
        $this->get(route('attachments.show', $file))->assertOk();                                   // 有保養檢視權限
        $this->actingAs($this->makeUserWithPermissions(['repairs.create']))->get(route('attachments.show', $file))->assertForbidden();   // 沒有就不行
    }

    public function test_result_attachments_follow_the_five_file_five_megabyte_limit(): void
    {
        $this->actingAs($reporter = $this->reporter());
        $order = $this->dispatchedTo($reporter);

        $this->report($order, ['attachments' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]])->assertSessionHasErrors('attachments');
        $this->report($order, ['attachments' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6))])->assertSessionHasErrors('attachments');
        $this->assertFalse($order->fresh()->result()->exists());
    }

    // ---------- NG 的狀態顯示 ----------

    public function test_an_ng_order_shows_converted_to_repair_instead_of_completed(): void
    {
        $this->actingAs($reporter = $this->reporter());
        $device = \App\Models\Device::create([
            'device_code' => 'NG-1', 'status' => 'normal',
            'device_category_id' => \App\Models\DeviceCategory::create(['name' => '飲水機'])->id,
            'classroom_id' => \App\Models\Classroom::create(['campus' => '本校區', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1 教室'])->id,
        ]);
        $order = $this->dispatchedTo($reporter, ['device_id' => $device->id]);

        $this->report($order, ['result' => 'ng', 'note' => '漏水', 'ng_device_ids' => [$order->device_id]])->assertRedirect();
        $order = $order->fresh(['result']);

        $this->assertSame(MaintenanceResult::NG_CONVERSION_CONVERTED, $order->result->ng_conversion_status);
        $this->assertSame('converted', $order->status);                // 已轉報修：轉出的報修單全部結案後才會變已完成
        $this->assertSame('已轉報修', $order->statusLabel()[0]);
        // 列表的狀態徽章：寫「已轉報修」，不是「已完成」（篩選下拉選單本來就有「已完成」選項，所以檢查徽章本身）。
        $this->get(route('maintenance-orders.index'))->assertSee('text-bg-info">已轉報修</span>', false)->assertDontSee('text-bg-success">已完成</span>', false);
        $this->get(route('maintenance-orders.show', $order))->assertSee('已轉報修');
    }

    public function test_an_ok_order_still_shows_completed(): void
    {
        $this->actingAs($reporter = $this->reporter());
        $order = $this->dispatchedTo($reporter);

        $this->report($order)->assertRedirect();

        $this->assertSame('已完成', $order->fresh(['result'])->statusLabel()[0]);
    }

    // ---------- 下次到期日往後推 ----------

    private function plan(string $nextDue, int $cycle = 30): MaintenancePlan
    {
        return MaintenancePlan::factory()->create(['cycle_days' => $cycle, 'next_due_date' => $nextDue, 'start_date' => '2026-01-01', 'is_active' => true]);
    }

    public function test_finishing_the_current_order_moves_the_plan_to_the_next_cycle(): void
    {
        $plan = $this->plan(now()->addDays(2)->toDateString(), 30);
        $this->actingAs($reporter = $this->reporter());
        $order = $this->dispatchedTo($reporter, ['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'scheduled_date' => $plan->next_due_date]);
        $this->report($order)->assertRedirect();

        $this->assertSame(now()->addDays(32)->toDateString(), $plan->fresh()->next_due_date->toDateString());   // 排定日 + 30 天
    }

    public function test_a_very_late_order_skips_missed_cycles_instead_of_creating_a_backlog(): void
    {
        $plan = $this->plan(now()->subDays(100)->toDateString(), 30);
        $this->actingAs($reporter = $this->reporter());
        $order = $this->dispatchedTo($reporter, ['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'scheduled_date' => $plan->next_due_date]);
        $this->report($order)->assertRedirect();

        $next = $plan->fresh()->next_due_date;
        $this->assertTrue($next->greaterThanOrEqualTo(now()->startOfDay()));          // 不會停在過去
        $this->assertTrue($next->lessThanOrEqualTo(now()->addDays(30)->endOfDay()));  // 也不會跳太遠
    }

    public function test_an_order_that_is_not_the_plans_current_cycle_does_not_move_the_plan(): void
    {
        $plan = $this->plan(now()->addDays(10)->toDateString(), 30);
        $this->actingAs($reporter = $this->reporter());
        $manual = $this->dispatchedTo($reporter, ['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'scheduled_date' => now()->addDays(1)]);
        $this->report($manual)->assertRedirect();

        $this->assertSame(now()->addDays(10)->toDateString(), $plan->fresh()->next_due_date->toDateString());
    }

    public function test_cancelling_the_current_order_also_moves_the_plan_so_it_does_not_stall(): void
    {
        $plan = $this->plan(now()->addDays(2)->toDateString(), 14);
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date]);

        $this->actingAs($this->manager())->delete(route('maintenance-orders.destroy', $order))->assertSessionHas('success');

        $this->assertSame(now()->addDays(16)->toDateString(), $plan->fresh()->next_due_date->toDateString());
        $this->assertSame(0, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count(), '新的到期日在 16 天後，還不用產生');
    }
}
