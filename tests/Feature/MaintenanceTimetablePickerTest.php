<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 保養排日期時，直接看設備位置（設備主檔記的教室）的一週課表，挑沒課的日子。
 */
class MaintenanceTimetablePickerTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function deviceInClassroom(string $code = 'DEV-TT1'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);
        $classroom = Classroom::firstOrCreate(['classroom_code' => 'A101'], ['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'normal', 'is_core' => false]);
    }

    public function test_the_device_timetable_returns_the_week_of_the_classroom_the_device_is_in(): void
    {
        $device = $this->deviceInClassroom();
        $this->actingAs($this->makeItManager());

        $json = $this->getJson(route('devices.timetable', $device))->assertOk()->json();

        $this->assertSame('A101', $json['classroom']['classroom_code']);
        $this->assertCount(5, $json['days']);
        $this->assertArrayHasKey('notes', $json['days'][0]);
        $this->assertNotEmpty($json['days'][0]['periods']);
    }

    public function test_other_unfinished_orders_in_the_same_classroom_show_up_as_notes_on_that_day(): void
    {
        $device = $this->deviceInClassroom();
        $neighbour = $this->deviceInClassroom('DEV-TT2');
        $day = now()->startOfWeek()->addDays(2)->toDateString();
        $order = MaintenanceOrder::factory()->create(['device_id' => $neighbour->id, 'status' => MaintenanceOrder::STATUS_PENDING, 'scheduled_date' => $day]);
        $this->actingAs($this->makeItManager());

        $json = $this->getJson(route('devices.timetable', ['device' => $device, 'date' => $day]))->assertOk()->json();
        $notes = collect($json['days'])->firstWhere('date', $day)['notes'];

        $this->assertSame([$order->displayNumber()], $notes);
    }

    public function test_only_people_who_manage_maintenance_can_read_it(): void
    {
        $device = $this->deviceInClassroom();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->getJson(route('devices.timetable', $device))->assertForbidden();
        $this->actingAs($this->makeTechnician())->getJson(route('devices.timetable', $device))->assertForbidden();
    }

    public function test_the_edit_page_and_the_ai_approval_page_offer_the_timetable_button(): void
    {
        $device = $this->deviceInClassroom();
        $order = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_PENDING]);
        $candidate = PreventiveCandidate::create([
            'device_id' => $device->id, 'risk_score' => 0.8, 'threshold' => 0.5, 'algorithm' => 'rule_based',
            'explanation' => [], 'features' => [], 'status' => PreventiveCandidate::STATUS_PENDING,
        ]);
        $this->actingAs($this->makeItManager());
        $url = route('devices.timetable', $device);

        $this->get(route('maintenance-orders.edit', $order))->assertOk()->assertSee('id="timetableModal"', false)->assertSee('data-target-input="scheduled_date"', false)->assertSee('A101 教室');
        $this->get(route('preventive-candidates.index'))->assertOk()->assertSee('data-url="' . $url . '"', false)->assertSee('data-target-input="scheduled_date_' . $candidate->id . '"', false);
    }

    public function test_the_repair_dispatch_page_still_shows_its_timetable_button(): void
    {
        $device = $this->deviceInClassroom();
        $repair = RepairRequest::factory()->create(['device_id' => $device->id, 'classroom_id' => null, 'status' => 'pending']);
        $this->actingAs($this->makeItManager());

        $this->get(route('repairs.show', $repair))->assertOk()->assertSee('A101 教室 的課表挑空堂', false)->assertSee('id="timetableModal"', false)
            ->assertSee('data-mode="datetime"', false);
        $this->getJson(route('repairs.timetable', $repair))->assertOk()->assertJsonPath('classroom.classroom_code', 'A101');
    }
}
