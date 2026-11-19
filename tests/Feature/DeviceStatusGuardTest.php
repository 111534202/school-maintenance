<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 設備狀態的兩道防線：
 * 1. 已淘汰（或已停用刪除）的設備不能報修（表單送出、掃描查詢、入口頁、網址帶入都要擋）。
 * 2. 設備還有沒結案的報修單時，不能從「維修中／已淘汰／停用」直接改回「正常」。
 */
class DeviceStatusGuardTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $status = 'normal', string $code = 'DEV-G1'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);
        $room = Classroom::firstOrCreate(['classroom_code' => 'G101'], ['campus' => '本', 'building' => 'G', 'floor' => '1', 'name' => 'G101 教室']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => $status]);
    }

    private function reportPayload(Device $device): array
    {
        return ['title' => '測試報修', 'description' => '壞了', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_id' => $device->id];
    }

    private function updatePayload(Device $device, string $status): array
    {
        return [
            'device_code' => $device->device_code, 'device_category_id' => $device->device_category_id, 'classroom_id' => $device->classroom_id,
            'status' => $status,
        ];
    }

    // ---------- 已淘汰設備不能報修 ----------

    public function test_a_retired_device_cannot_be_reported(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $retired = $this->device('retired');

        $this->actingAs($teacher)->post(route('repairs.store'), $this->reportPayload($retired))->assertSessionHasErrors('device_note');
        $this->assertSame(0, RepairRequest::count());
    }

    public function test_a_normal_device_can_still_be_reported(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->actingAs($teacher)->post(route('repairs.store'), $this->reportPayload($this->device('normal')))->assertSessionHasNoErrors();
        $this->assertSame(1, RepairRequest::count());
    }

    public function test_a_disabled_deleted_device_cannot_be_reported(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $device = $this->device('normal');
        $device->delete();   // 停用＝軟刪除

        $this->actingAs($teacher)->post(route('repairs.store'), $this->reportPayload($device))->assertSessionHasErrors('device_id');
        $this->assertSame(0, RepairRequest::count());
    }

    public function test_the_scan_lookup_refuses_a_retired_device_with_a_reason(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $retired = $this->device('retired', 'DEV-OLD');
        $normal = $this->device('normal', 'DEV-NEW');

        $this->actingAs($teacher)->getJson(route('repairs.device-lookup', ['code' => 'DEV-OLD']))
            ->assertStatus(422)->assertJsonPath('message', '設備 DEV-OLD 已淘汰，無法報修。');
        $this->actingAs($teacher)->getJson(route('repairs.device-lookup', ['code' => 'DEV-NEW']))->assertOk()->assertJsonPath('id', $normal->id);
    }

    public function test_the_report_form_does_not_prefill_a_retired_device_and_explains_why(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $retired = $this->device('retired', 'DEV-OLD');

        $this->actingAs($teacher)->get(route('repairs.create', ['device' => $retired->id]))->assertOk()
            ->assertSee('設備 DEV-OLD 已淘汰，無法報修。');
    }

    public function test_the_entry_page_of_a_retired_device_has_a_disabled_report_button(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $retired = $this->device('retired', 'DEV-OLD');
        $normal = $this->device('normal', 'DEV-NEW');

        $this->actingAs($teacher)->get(route('devices.entry', $retired))->assertOk()
            ->assertSee('此設備已淘汰，無法報修')->assertDontSee(route('repairs.create', ['device' => $retired->id]), false);
        $this->actingAs($teacher)->get(route('devices.entry', $normal))->assertOk()
            ->assertSee(route('repairs.create', ['device' => $normal->id]), false);
    }

    // ---------- 有未結案報修單時不能直接改回正常 ----------

    public function test_a_device_with_an_open_repair_cannot_be_switched_back_to_normal(): void
    {
        $device = $this->device('repairing');
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'in_progress']);

        $this->actingAs($this->makeItManager())->put(route('devices.update', $device), $this->updatePayload($device, 'normal'))
            ->assertSessionHasErrors('status');
        $this->assertSame('repairing', $device->fresh()->status);
    }

    public function test_a_device_can_be_switched_back_to_normal_when_all_repairs_are_closed(): void
    {
        $device = $this->device('repairing');
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'completed']);

        $this->actingAs($this->makeItManager())->put(route('devices.update', $device), $this->updatePayload($device, 'normal'))
            ->assertSessionHasNoErrors();
        $this->assertSame('normal', $device->fresh()->status);
    }

    public function test_editing_other_fields_of_a_normal_device_with_an_open_repair_is_not_blocked(): void
    {
        // 還沒開始維修的報修單（設備仍是正常）：只改品牌等欄位、狀態維持正常，不能被擋。
        $device = $this->device('normal');
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'pending']);

        $this->actingAs($this->makeItManager())->put(route('devices.update', $device), $this->updatePayload($device, 'normal') + ['brand' => '新品牌'])
            ->assertSessionHasNoErrors();
        $this->assertSame('新品牌', $device->fresh()->brand);
    }

    public function test_the_status_error_is_shown_on_the_edit_form(): void
    {
        $device = $this->device('repairing');
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'assigned']);
        $manager = $this->makeItManager();

        $this->actingAs($manager)->from(route('devices.edit', $device))->followingRedirects()
            ->put(route('devices.update', $device), $this->updatePayload($device, 'normal'))
            ->assertOk()->assertSee('還有沒結案的報修單');
    }
}
