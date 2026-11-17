<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 保養「待轉報修」：點下去直接跳到新增報修畫面，設備與故障描述都先填好；送出後記回保養結果，不會重複建單。
 */
class NgToRepairFormTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function pendingNgResult(): MaintenanceResult
    {
        $category = DeviceCategory::create(['name' => '投影機']);
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);
        $device = Device::create(['device_code' => 'DEV-NG1', 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'normal', 'is_core' => false]);
        $order = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);

        return MaintenanceResult::factory()->create([
            'maintenance_order_id' => $order->id, 'result' => 'ng', 'note' => '燈泡亮度不足',
            'ng_conversion_status' => MaintenanceResult::NG_CONVERSION_PENDING, 'repair_request_id' => null,
        ]);
    }

    public function test_the_repair_form_opens_with_the_device_and_problem_already_filled(): void
    {
        $result = $this->pendingNgResult();
        $this->actingAs($this->makeItManager());

        $this->get(route('repairs.create', ['from_result' => $result->id]))
            ->assertOk()
            ->assertSee('DEV-NG1')
            ->assertSee('保養檢查 NG：DEV-NG1')
            ->assertSee('燈泡亮度不足')
            ->assertSee('name="maintenance_result_id" value="' . $result->id . '"', false);
    }

    public function test_submitting_links_the_new_repair_back_and_cannot_be_done_twice(): void
    {
        $result = $this->pendingNgResult();
        $this->actingAs($this->makeItManager());
        $device = Device::first();
        $payload = [
            'title' => '保養檢查 NG：DEV-NG1', 'description' => '燈泡亮度不足', 'impact_level' => 'medium', 'is_class_affected' => 0,
            'device_id' => $device->id, 'maintenance_result_id' => $result->id,
        ];

        $this->post(route('repairs.store'), $payload)->assertRedirect();

        $result->refresh();
        $this->assertSame(MaintenanceResult::NG_CONVERSION_CONVERTED, $result->ng_conversion_status);
        $repair = RepairRequest::findOrFail($result->repair_request_id);
        $this->assertSame($device->id, $repair->device_id);

        // 已經轉過：再帶同一筆結果進表單，不會再預先填入，也不會把第二張單記回去。
        $this->get(route('repairs.create', ['from_result' => $result->id]))->assertOk()->assertDontSee('保養檢查 NG：DEV-NG1');
        $this->post(route('repairs.store'), $payload)->assertRedirect();
        $this->assertSame($repair->id, $result->fresh()->repair_request_id);
    }

    public function test_people_without_maintenance_permission_get_an_empty_form(): void
    {
        $result = $this->pendingNgResult();
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.create', ['from_result' => $result->id]))->assertOk()->assertDontSee('燈泡亮度不足')->assertDontSee('DEV-NG1');
    }

    public function test_the_pending_badge_and_result_page_offer_a_create_repair_button(): void
    {
        $result = $this->pendingNgResult();
        $this->actingAs($this->makeItManager());
        $link = route('repairs.create', ['from_result' => $result->id]);

        $this->get(route('maintenance-orders.index'))->assertSee($link, false);
        $this->get(route('maintenance-orders.show', $result->maintenance_order_id))->assertSee($link, false);
        $this->get(route('maintenance-orders.results.show', $result->maintenance_order_id))->assertSee($link, false);
    }
}
