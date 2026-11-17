<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 保養工單的「進行中」：負責人按「開始保養」→ 待處理變進行中並記下開始時間；別人不能按、不能重複按。
 */
class MaintenanceStartTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_the_assignee_can_start_and_the_status_becomes_in_progress(): void
    {
        $technician = $this->makeTechnician();
        $order = MaintenanceOrder::factory()->create(['status' => MaintenanceOrder::STATUS_PENDING, 'assigned_to' => $technician->id]);

        $this->actingAs($technician)->patch(route('maintenance-orders.start', $order))->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(MaintenanceOrder::STATUS_IN_PROGRESS, $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertSame('進行中', $order->statusLabel()[0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'loggable_type' => MaintenanceOrder::class, 'loggable_id' => $order->id]);

        // 進行中的工單還是能回報結果，而且列表篩選「進行中」找得到。
        $this->actingAs($technician)->get(route('maintenance-orders.index', ['status' => 'in_progress']))->assertOk()->assertSee('進行中');
        $this->actingAs($technician)->get(route('maintenance-orders.results.create', $order))->assertOk();
    }

    public function test_it_cannot_be_started_twice_or_by_someone_else_or_after_reporting(): void
    {
        $technician = $this->makeTechnician();
        $other = $this->makeUserWithRole('technician', '維修人員');
        $other->forceFill(['id' => $other->id])->save();
        $order = MaintenanceOrder::factory()->create(['status' => MaintenanceOrder::STATUS_PENDING, 'assigned_to' => $technician->id]);

        // 指派給別人的工單，不是負責人不能按。
        $stranger = User::factory()->create(['role_id' => $technician->role_id]);
        $this->actingAs($stranger)->patch(route('maintenance-orders.start', $order))->assertForbidden();
        $this->assertSame(MaintenanceOrder::STATUS_PENDING, $order->fresh()->status);

        $this->actingAs($technician)->patch(route('maintenance-orders.start', $order))->assertSessionHas('success');
        $this->actingAs($technician)->patch(route('maintenance-orders.start', $order))->assertSessionHas('error');

        // 沒有保養回報權限的人（例如教師）連按鈕的路由都進不去。
        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->patch(route('maintenance-orders.start', $order))->assertForbidden();
    }

    public function test_the_start_button_only_shows_to_people_who_can_start(): void
    {
        $technician = $this->makeTechnician();
        $order = MaintenanceOrder::factory()->create(['status' => MaintenanceOrder::STATUS_PENDING, 'assigned_to' => $technician->id]);

        $this->actingAs($technician)->get(route('maintenance-orders.show', $order))->assertSee('開始保養');
        // 只能查看保養資料的主管看不到按鈕。
        $this->actingAs($this->makeUserWithRole('executive', '主管'))->get(route('maintenance-orders.show', $order))->assertDontSee('開始保養');
    }
}
