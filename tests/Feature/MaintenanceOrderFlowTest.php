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
 * 保養工單流程：逾期標示、7 天內到期通知、指派負責人（只有負責人與管理者能回報）、
 * 回報時可上傳附件、NG 直接顯示「已轉報修」、做完或取消後計畫的下次到期日往後推一個週期。
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

        $this->actingAs($this->manager())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $reporter->id])->assertSessionHas('success');

        $this->assertSame($reporter->id, $order->fresh()->assigned_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assigned', 'loggable_id' => $order->id]);
        $this->get(route('maintenance-orders.show', $order))->assertSee($reporter->name);

        $this->patch(route('maintenance-orders.assign', $order), ['assigned_to' => ''])->assertSessionHas('success');   // 取消指派
        $this->assertNull($order->fresh()->assigned_to);
    }

    public function test_only_people_who_can_report_can_be_assigned_and_only_managers_can_assign(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $viewerOnly = $this->makeUserWithPermissions(['maintenance.view']);

        $this->actingAs($this->manager())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $viewerOnly->id])->assertSessionHasErrors('assigned_to');
        $this->assertNull($order->fresh()->assigned_to);

        $this->actingAs($this->reporter())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $this->reporter()->id])->assertForbidden();
    }

    public function test_a_completed_order_cannot_be_assigned(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'completed']);

        $this->actingAs($this->manager())->patch(route('maintenance-orders.assign', $order), ['assigned_to' => $this->reporter()->id])->assertSessionHas('error');
        $this->assertNull($order->fresh()->assigned_to);
    }

    public function test_an_assigned_order_can_only_be_reported_by_the_assignee_or_a_manager(): void
    {
        $assignee = $this->reporter();
        $stranger = $this->reporter();
        $order = MaintenanceOrder::factory()->create(['status' => 'pending', 'assigned_to' => $assignee->id]);

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

    public function test_an_order_without_an_assignee_can_be_reported_by_anyone_who_may_report(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending', 'assigned_to' => null]);

        $this->actingAs($this->reporter());
        $this->report($order)->assertRedirect();
        $this->assertTrue($order->fresh()->result()->exists());
    }

    // ---------- 附件 ----------

    public function test_a_result_can_have_attachments_that_are_shown_and_downloadable_with_view_permission(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->reporter());

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
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->reporter());

        $this->report($order, ['attachments' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]])->assertSessionHasErrors('attachments');
        $this->report($order, ['attachments' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6))])->assertSessionHasErrors('attachments');
        $this->assertFalse($order->fresh()->result()->exists());
    }

    // ---------- NG 的狀態顯示 ----------

    public function test_an_ng_order_shows_converted_to_repair_instead_of_completed(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->reporter());

        $this->report($order, ['result' => 'ng', 'note' => '漏水'])->assertRedirect();
        $order = $order->fresh(['result']);

        $this->assertSame(MaintenanceResult::NG_CONVERSION_CONVERTED, $order->result->ng_conversion_status);
        $this->assertSame('completed', $order->status);                // 資料庫狀態仍是 completed（檢查動作做完了）
        $this->assertSame('已轉報修', $order->statusLabel()[0]);       // 畫面直接寫去向
        // 列表的狀態徽章：寫「已轉報修」，不是「已完成」（篩選下拉選單本來就有「已完成」選項，所以檢查徽章本身）。
        $this->get(route('maintenance-orders.index'))->assertSee('text-bg-info">已轉報修</span>', false)->assertDontSee('text-bg-success">已完成</span>', false);
        $this->get(route('maintenance-orders.show', $order))->assertSee('已轉報修');
    }

    public function test_an_ok_order_still_shows_completed(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->reporter());

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
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date]);

        $this->actingAs($this->reporter());
        $this->report($order)->assertRedirect();

        $this->assertSame(now()->addDays(32)->toDateString(), $plan->fresh()->next_due_date->toDateString());   // 排定日 + 30 天
    }

    public function test_a_very_late_order_skips_missed_cycles_instead_of_creating_a_backlog(): void
    {
        $plan = $this->plan(now()->subDays(100)->toDateString(), 30);
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date]);

        $this->actingAs($this->reporter());
        $this->report($order)->assertRedirect();

        $next = $plan->fresh()->next_due_date;
        $this->assertTrue($next->greaterThanOrEqualTo(now()->startOfDay()));          // 不會停在過去
        $this->assertTrue($next->lessThanOrEqualTo(now()->addDays(30)->endOfDay()));  // 也不會跳太遠
    }

    public function test_an_order_that_is_not_the_plans_current_cycle_does_not_move_the_plan(): void
    {
        $plan = $this->plan(now()->addDays(10)->toDateString(), 30);
        $manual = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => now()->addDays(1)]);

        $this->actingAs($this->reporter());
        $this->report($manual)->assertRedirect();

        $this->assertSame(now()->addDays(10)->toDateString(), $plan->fresh()->next_due_date->toDateString());
    }

    public function test_cancelling_the_current_order_also_moves_the_plan_so_it_does_not_stall(): void
    {
        $plan = $this->plan(now()->addDays(2)->toDateString(), 14);
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date]);

        $this->actingAs($this->manager())->delete(route('maintenance-orders.destroy', $order))->assertSessionHas('success');

        $this->assertSame(now()->addDays(16)->toDateString(), $plan->fresh()->next_due_date->toDateString());
    }
}
