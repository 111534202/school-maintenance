<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\KnowledgeBase;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 11/23 劉家芸提出的調整：登入逾時時對話框裡的登入頁改成整頁、設備條碼框可以打字搜尋、
 * 知識庫一般使用者不顯示「已上架」、保養計畫又可以有好幾個對象（一張工單、NG 從全部對象勾選設備）。
 */
class UiTweaks1123Test extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code, ?DeviceCategory $category = null, string $status = 'normal'): Device
    {
        $room = Classroom::firstOrCreate(['classroom_code' => 'UI-1'], ['campus' => '本', 'building' => 'U', 'floor' => '1', 'name' => 'UI 教室']);
        $category ??= DeviceCategory::firstOrCreate(['name' => '投影機']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => $status]);
    }

    // 登入頁如果被開在對話框裡（登入逾時後點開資料），會把整個畫面重新整理，轉到完整的登入頁。
    public function test_the_login_page_breaks_out_of_a_dialog(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('window.top !== window.self', false);
    }

    // 新增報修、修改報修單（沒綁定設備）的設備條碼框接上設備清單，可以打字搜尋；已淘汰的設備不列。
    public function test_the_device_code_box_has_a_searchable_list(): void
    {
        $this->device('DEV-LIST');
        $this->device('DEV-GONE', null, 'retired');
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->get(route('repairs.create'))->assertOk()
            ->assertSee('<datalist id="device_code_options">', false)
            ->assertSee('<option value="DEV-LIST">', false)->assertDontSee('<option value="DEV-GONE">', false)
            ->assertSee("input.setAttribute('list', 'device_code_options')", false);

        $repair = RepairRequest::factory()->create(['device_id' => null, 'status' => 'pending', 'device_note' => 'x']);
        $this->get(route('repairs.edit', $repair))->assertOk()->assertSee('<datalist id="device_code_options">', false);
    }

    // 知識庫文章：只有知識庫管理者看得到「已上架／未上架」。
    public function test_the_published_badge_is_only_for_knowledge_base_managers(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '電腦黑畫面', 'is_published' => true]);

        $this->actingAs($this->makeUserWithPermissions(['knowledge-base.view']))->get(route('knowledge-base.show', $entry))->assertOk()->assertDontSee('已上架');
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route('knowledge-base.show', $entry))->assertOk()->assertSee('已上架');
    }

    // 一張工單有一台設備＋一個設備類別：NG 要從全部對象勾選（指定的設備＋那一類還在用的設備），每台各建一張報修單。
    public function test_ng_on_a_multi_target_order_picks_devices_from_all_targets(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $aircon = DeviceCategory::create(['name' => '冷氣']);
        $proj = $this->device('PROJ-1');
        $ac1 = $this->device('AC-1', $aircon);
        $this->device('AC-OLD', $aircon, 'retired');
        $plan = MaintenancePlan::create(['name' => '綜合月檢', 'description' => '清潔', 'device_id' => $proj->id, 'cycle_days' => 30,
            'start_date' => now()->subMonth()->toDateString(), 'next_due_date' => now()->addDay()->toDateString(), 'is_active' => true]);
        $plan->targets()->create(['device_id' => $proj->id]);
        $plan->targets()->create(['device_category_id' => $aircon->id]);

        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('success');
        $order = MaintenanceOrder::sole();
        $order->update(['status' => MaintenanceOrder::STATUS_IN_PROGRESS, 'assigned_to' => $admin->id]);

        $this->get(route('maintenance-orders.results.create', $order))->assertOk()
            ->assertSee('name="ng_device_ids[]"', false)->assertSee('PROJ-1')->assertSee('AC-1')->assertDontSee('AC-OLD');
        $base = ['result' => 'ng', 'executed_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'note' => '異常'];
        $this->post(route('maintenance-orders.results.store', $order), $base)->assertSessionHasErrors('ng_device_ids');
        $this->post(route('maintenance-orders.results.store', $order), $base + ['ng_device_ids' => [$proj->id, $ac1->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$proj->id, $ac1->id], RepairRequest::pluck('device_id')->all());
    }
}
