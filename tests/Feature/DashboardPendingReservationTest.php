<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\Concerns\CreatesReservations;
use Tests\TestCase;

/**
 * 主控台「空間預約待審核」卡片，以及預約列表的申請狀態篩選。
 */
class DashboardPendingReservationTest extends TestCase
{
    use CreatesReservations;
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室',
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    private function reservation(User $requester, string $status, string $className, string $start = '09:00', string $end = '10:00'): ClassroomReservation
    {
        return $this->makeReservation([
            'classroom_id' => $this->classroom->id,
            'requester_id' => $requester->id,
            'class_name' => $className,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'purpose' => '班級活動',
            'status' => $status,
        ]);
    }

    public function test_dashboard_card_counts_pending_reservations_and_links_to_the_filtered_list(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $other = $this->makeUserWithRole('teacher', '教師');
        $this->reservation($teacher, 'pending', '一年甲班');
        $this->reservation($other, 'pending', '二年乙班');
        $this->reservation($other, 'approved', '三年丙班', '13:00', '14:00');   // 不同時段，不會讓待審核的被自動拒絕

        // 預約管理者：算全部待審核。
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('空間預約待審核')
            ->assertSee('href="' . route('reservations.index', ['status' => 'pending']) . '"', false)
            ->assertViewHas('pendingReservationCount', 2);

        // 一般使用者：只算自己提出的。
        $this->actingAs($teacher)->get(route('dashboard'))->assertViewHas('pendingReservationCount', 1);
    }

    public function test_reservation_list_can_show_only_pending_or_reviewed_requests(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->reservation($teacher, 'pending', '一年甲班');
        $this->reservation($teacher, 'approved', '三年丙班', '13:00', '14:00');
        $this->reservation($teacher, 'partially_approved', '四年丁班', '15:00', '16:00');

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->get(route('reservations.index', ['status' => 'pending']))
            ->assertOk()->assertSee('一年甲班')->assertDontSee('三年丙班')->assertDontSee('四年丁班');

        $this->get(route('reservations.index', ['status' => 'reviewed']))
            ->assertOk()->assertDontSee('一年甲班')->assertSee('三年丙班')->assertSee('四年丁班');

        $this->get(route('reservations.index'))
            ->assertOk()->assertSee('一年甲班')->assertSee('三年丙班');
    }
}
