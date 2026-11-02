<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 派工時的「建議處理時間」：可以打開設備地點那間教室的一週課表挑空堂。
 */
class RepairDispatchTimetableTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function classroom(array $attrs = []): Classroom
    {
        // IT-301 在 config/timetable.php 有示範課程（星期一第 1、2、4 節）。
        return Classroom::create($attrs + ['campus' => '本', 'building' => 'A', 'floor' => '3', 'classroom_code' => 'IT-301', 'name' => '資訊教室一']);
    }

    public function test_dispatch_page_offers_the_timetable_of_the_repair_location(): void
    {
        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $repair = RepairRequest::factory()->create(['status' => 'pending', 'classroom_id' => $this->classroom()->id]);

        $this->get(route('repairs.show', $repair))
            ->assertOk()
            ->assertSee('建議處理時間')
            ->assertSee('從 資訊教室一 的課表挑空堂')
            ->assertSee(route('repairs.timetable', $repair), false);
    }

    public function test_timetable_shows_courses_reservations_and_works_for_abnormal_classrooms(): void
    {
        Carbon::setTestNow('2026-11-02 08:00:00');   // 星期一
        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $room = $this->classroom(['reservation_status' => 'abnormal']);   // 設備異常的教室也要查得到
        ClassroomReservation::create([
            'classroom_id' => $room->id, 'requester_id' => $this->makeUserWithRole('teacher', '教師')->id, 'class_name' => '資二忠',
            'reservation_date' => '2026-11-03', 'start_time' => '09:10', 'end_time' => '10:00', 'status' => 'approved',
        ]);
        $repair = RepairRequest::factory()->create(['status' => 'pending', 'classroom_id' => $room->id]);

        $response = $this->getJson(route('repairs.timetable', [$repair, 'date' => '2026-11-04']))->assertOk();

        $response->assertJsonPath('week_start', '2026-11-02')
            ->assertJsonPath('week_end', '2026-11-06')
            ->assertJsonPath('days.0.periods.0.is_class', true)             // 星期一第 1 節有課
            ->assertJsonPath('days.0.periods.2.is_class', false)            // 星期一第 3 節空堂
            ->assertJsonPath('days.1.periods.1.reservations.0.status', 'approved');   // 星期二第 2 節已被預約
        Carbon::setTestNow();
    }

    public function test_timetable_uses_the_device_classroom_when_no_location_was_chosen_and_explains_when_there_is_none(): void
    {
        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $noLocation = RepairRequest::factory()->create(['status' => 'pending', 'device_id' => null, 'classroom_id' => null]);

        $this->getJson(route('repairs.timetable', $noLocation))->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_timetable_requires_the_dispatch_permission(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'pending', 'classroom_id' => $this->classroom()->id]);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->getJson(route('repairs.timetable', $repair))->assertForbidden();
    }
}
