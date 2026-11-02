<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Services\ClassroomRepairNotices;
use App\Services\DeviceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 空間預約結合設備狀態：教室有設備在報修時，申請與審核畫面都會提醒「投影機（DEV-xxxx）正在報修」。
 */
class ReservationRepairNoticeTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->classroom = $this->makeClassroom('A101');
    }

    private function makeClassroom(string $code, array $attrs = []): Classroom
    {
        return Classroom::create($attrs + [
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => $code, 'name' => $code . ' 教室',
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    private function makeDevice(Classroom $classroom, string $code, string $category, bool $core = false, string $status = 'normal'): Device
    {
        return Device::create([
            'device_code' => $code,
            'device_category_id' => DeviceCategory::firstOrCreate(['name' => $category])->id,
            'classroom_id' => $classroom->id,
            'status' => $status,
            'is_core' => $core,
        ]);
    }

    public function test_open_repair_of_a_device_is_listed_for_its_classroom(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0003', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'in_progress']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id]);

        $this->assertCount(1, $notices[$this->classroom->id]);
        $this->assertStringContainsString('投影機（DEV-0003）正在報修', $notices[$this->classroom->id][0]['text']);
    }

    public function test_completed_repairs_and_other_classrooms_are_not_listed(): void
    {
        $other = $this->makeClassroom('B201');
        $projector = $this->makeDevice($this->classroom, 'DEV-0003', '投影機');
        $otherDevice = $this->makeDevice($other, 'DEV-0009', '電腦');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'completed']);
        RepairRequest::factory()->create(['device_id' => $otherDevice->id, 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id]);

        $this->assertArrayNotHasKey($this->classroom->id, $notices);
    }

    public function test_repair_without_device_but_with_classroom_is_listed(): void
    {
        RepairRequest::factory()->create(['device_id' => null, 'classroom_id' => $this->classroom->id, 'title' => '冷氣不冷', 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id]);

        $this->assertStringContainsString('報修案件「冷氣不冷」', $notices[$this->classroom->id][0]['text']);
    }

    public function test_create_form_warns_about_devices_under_repair_but_still_allows_booking(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0003', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'assigned']);

        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('data-id="' . $this->classroom->id . '"', false)   // 仍可選
            ->assertSee('投影機（DEV-0003）正在報修')
            ->assertSee('classroom_repair_notice', false);
    }

    public function test_classroom_with_a_broken_core_device_is_listed_as_suspended_with_the_reason(): void
    {
        $broken = $this->makeClassroom('C301');
        $projector = $this->makeDevice($broken, 'DEV-0100', '投影機', core: true);
        DeviceStatusService::updateStatus($projector, 'repairing');
        $this->assertSame('abnormal', $broken->fresh()->reservation_status);

        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('C301 教室')
            ->assertDontSee('data-id="' . $broken->id . '"', false)   // 不能選
            ->assertSee('暫停預約')
            ->assertSee('投影機（DEV-0100）維修中');
    }

    public function test_reservation_detail_page_shows_the_classroom_device_status(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0003', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'in_progress']);

        $reservation = ClassroomReservation::create([
            'classroom_id' => $this->classroom->id,
            'requester_id' => auth()->id(),
            'class_name' => '一年甲班',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => '班級活動',
            'status' => 'pending',
        ]);

        $this->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('教室設備狀況')
            ->assertSee('投影機（DEV-0003）正在報修');
    }
}
