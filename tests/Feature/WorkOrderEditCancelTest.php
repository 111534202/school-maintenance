<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use App\Services\DueMaintenanceOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 工單（報修單、保養工單）的修改與取消（刪除）：
 * - 修改：只有有派工權限（報修單 repairs.dispatch）／保養管理權限（保養工單 maintenance.manage）的人能改；已結案、已完成的不能改。
 * - 取消與刪除是同一件事，做法是軟刪除：看板與統計不再列出，資料與操作紀錄保留；已經有維修紀錄、保養結果的不能取消。
 */
class WorkOrderEditCancelTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function dispatcher()
    {
        return $this->makeUserWithPermissions(['repairs.dispatch', 'repairs.create']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => '修改後的標題', 'description' => '修改後的描述', 'impact_level' => 'high',
            'is_class_affected' => '1', 'device_note' => '三樓走廊投影機', 'classroom_id' => '',
        ], $override);
    }

    // ---------- 報修單：修改 ----------

    public function test_a_dispatcher_can_edit_a_repair_request_and_it_is_logged(): void
    {
        $repair = RepairRequest::factory()->create(['title' => '原標題', 'status' => 'assigned', 'device_note' => '原設備']);
        $this->actingAs($this->dispatcher());

        $this->get(route('repairs.edit', $repair))->assertOk()->assertSee('原標題');
        $this->put(route('repairs.update', $repair), $this->payload())->assertRedirect(route('repairs.show', $repair))->assertSessionHas('success');

        $repair->refresh();
        $this->assertSame('修改後的標題', $repair->title);
        $this->assertSame('high', $repair->impact_level);
        $this->assertSame('assigned', $repair->status->value);   // 狀態不會被改動
        $log = AuditLog::where('action', 'updated')->where('loggable_id', $repair->id)->firstOrFail();
        $this->assertSame('原標題', $log->changes['title']['old']);
    }

    public function test_editing_still_needs_device_note_or_classroom_when_no_device_is_bound(): void
    {
        $repair = RepairRequest::factory()->create(['device_id' => null, 'status' => 'pending']);
        $this->actingAs($this->dispatcher());

        $this->put(route('repairs.update', $repair), $this->payload(['device_note' => '', 'classroom_id' => '']))->assertSessionHasErrors('device_note');
    }

    public function test_people_without_dispatch_permission_cannot_edit_or_cancel(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'pending']);
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.edit', $repair))->assertForbidden();
        $this->put(route('repairs.update', $repair), $this->payload())->assertForbidden();
        $this->delete(route('repairs.destroy', $repair))->assertForbidden();
        $this->assertNull($repair->fresh()->deleted_at);
    }

    public function test_a_completed_repair_request_cannot_be_edited(): void
    {
        $repair = RepairRequest::factory()->create(['title' => '已結案的單', 'status' => 'completed']);
        $this->actingAs($this->dispatcher());

        $this->put(route('repairs.update', $repair), $this->payload())->assertSessionHas('error');

        $this->assertSame('已結案的單', $repair->fresh()->title);
    }

    // ---------- 報修單：取消（刪除） ----------

    public function test_cancelling_a_repair_request_soft_deletes_it_and_removes_it_from_the_board_and_dashboard(): void
    {
        $repair = RepairRequest::factory()->create(['title' => '要取消的單', 'status' => 'pending']);
        $this->actingAs($this->dispatcher());
        $this->get(route('repairs.index'))->assertSee('要取消的單');

        $this->delete(route('repairs.destroy', $repair))->assertRedirect(route('repairs.index'))->assertSessionHas('success');

        $this->assertSoftDeleted('repair_requests', ['id' => $repair->id]);   // 資料還在，只是標記已取消
        $this->get(route('repairs.index'));   // 第一次打開會顯示「已取消」的成功訊息（訊息裡有標題），先讓它消耗掉
        $this->get(route('repairs.index'))->assertDontSee('要取消的單');
        $this->get(route('repairs.show', $repair))->assertNotFound();
        $this->assertSame(0, RepairRequest::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'loggable_id' => $repair->id]);
    }

    public function test_a_repair_request_with_a_repair_log_cannot_be_cancelled(): void
    {
        $this->actingAs($this->dispatcher());

        foreach (['pending_review', 'completed'] as $status) {
            $repair = RepairRequest::factory()->create(['status' => $status]);
            $this->delete(route('repairs.destroy', $repair))->assertSessionHas('error');
            $this->assertNull($repair->fresh()->deleted_at);
        }
    }

    public function test_show_page_offers_edit_and_cancel_only_when_allowed(): void
    {
        $pending = RepairRequest::factory()->create(['status' => 'pending']);
        $review = RepairRequest::factory()->create(['status' => 'pending_review']);
        $this->actingAs($this->dispatcher());

        $this->get(route('repairs.show', $pending))->assertSee('取消工單')->assertSee(route('repairs.edit', $pending), false);
        $this->get(route('repairs.show', $review))->assertDontSee('取消工單')->assertSee(route('repairs.edit', $review), false);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('repairs.show', $pending))->assertDontSee('取消工單')->assertDontSee(route('repairs.edit', $pending), false);
    }

    // ---------- 保養工單 ----------

    private function manager()
    {
        return $this->makeUserWithPermissions(['maintenance.manage', 'maintenance.view']);
    }

    public function test_a_maintenance_manager_can_change_the_scheduled_date(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => '2027-01-10']);
        $this->actingAs($this->manager());

        $this->get(route('maintenance-orders.edit', $order))->assertOk();
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2027-02-01'])->assertRedirect(route('maintenance-orders.show', $order));

        $this->assertSame('2027-02-01', $order->fresh()->scheduled_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'loggable_id' => $order->id]);
    }

    public function test_maintenance_order_edit_and_cancel_need_the_manage_permission(): void
    {
        $order = MaintenanceOrder::factory()->create(['status' => 'pending']);
        $this->actingAs($this->makeUserWithRole('technician', '維修人員'));   // 只有 maintenance.view／report

        $this->get(route('maintenance-orders.edit', $order))->assertForbidden();
        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2027-02-01'])->assertForbidden();
        $this->delete(route('maintenance-orders.destroy', $order))->assertForbidden();
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_cancelling_a_maintenance_order_soft_deletes_it_and_the_schedule_does_not_recreate_it(): void
    {
        $plan = MaintenancePlan::factory()->create(['is_active' => true, 'next_due_date' => now()->toDateString()]);
        $order = MaintenanceOrder::factory()->create([
            'maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'status' => 'pending', 'scheduled_date' => $plan->next_due_date,
        ]);
        $this->actingAs($this->manager());

        $this->delete(route('maintenance-orders.destroy', $order))->assertRedirect(route('maintenance-orders.index'))->assertSessionHas('success');

        $this->assertSoftDeleted('maintenance_orders', ['id' => $order->id]);
        $this->get(route('maintenance-orders.index'))->assertDontSee('#' . $order->id . '<', false);
        // 排程不會因為取消了，隔天又把同一期的工單重新產生出來。
        $this->assertSame(0, app(DueMaintenanceOrderGenerator::class)->generate());
        $this->assertSame(0, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());
    }

    public function test_completed_or_reported_maintenance_orders_cannot_be_edited_or_cancelled(): void
    {
        $this->actingAs($this->manager());

        $done = MaintenanceOrder::factory()->create(['status' => 'completed']);
        $this->put(route('maintenance-orders.update', $done), ['scheduled_date' => '2027-02-01'])->assertSessionHas('error');
        $this->delete(route('maintenance-orders.destroy', $done))->assertSessionHas('error');
        $this->assertNull($done->fresh()->deleted_at);

        $reported = MaintenanceOrder::factory()->create(['status' => 'in_progress']);
        MaintenanceResult::factory()->create(['maintenance_order_id' => $reported->id]);
        $this->delete(route('maintenance-orders.destroy', $reported))->assertSessionHas('error');
        $this->assertNull($reported->fresh()->deleted_at);
    }

}
