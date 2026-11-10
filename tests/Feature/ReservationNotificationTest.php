<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 通知鈴鐺：有空間申請審核權限（reservations.manage）的人，看得到「N 筆空間申請待審核」；
 * 已經逾期（時段已開始）的申請不算；點下去到空間申請、只看未審核。
 */
class ReservationNotificationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function reservation(string $date, string $status = 'pending'): ClassroomReservation
    {
        $classroom = Classroom::firstOrCreate(['classroom_code' => 'A101'], [
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'is_active' => true, 'reservation_status' => 'normal',
        ]);
        $reservation = ClassroomReservation::create([
            'classroom_id' => $classroom->id, 'requester_id' => User::factory()->create()->id, 'class_name' => '資五孝',
            'purpose' => '班級活動', 'status' => $status,
        ]);
        $reservation->slots()->create(['reservation_date' => $date, 'start_time' => '10:00', 'end_time' => '10:50']);

        return $reservation;
    }

    public function test_reviewers_see_pending_reservation_requests_in_the_bell(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $this->reservation('2026-11-10');
        $this->reservation('2026-11-12');
        $this->reservation('2026-11-05');               // 已逾期：不算
        $this->reservation('2026-11-11', 'approved');   // 已審核：不算

        $this->actingAs($this->makeUserWithPermissions(['reservations.manage']));

        $this->get(route('reservations.create'))
            ->assertSee('2 筆空間申請待審核')
            ->assertSee(e(route('reservations.index', ['status' => 'pending'])), false);
    }

    public function test_people_without_the_review_permission_do_not_see_it(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $this->reservation('2026-11-10');

        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));

        $this->get(route('reservations.create'))->assertDontSee('空間申請待審核');
    }
}
