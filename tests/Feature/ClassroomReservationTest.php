<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class ClassroomReservationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicant = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($this->applicant);
    }

    private function makeClassroom(string $code = 'A101', array $attrs = []): Classroom
    {
        return Classroom::create(array_merge([
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '1F',
            'classroom_code' => $code,
            'name' => $code . ' 教室',
            'type' => '一般教室',
            'is_active' => true,
            'reservation_status' => 'normal',
        ], $attrs));
    }

    private function payload(Classroom $classroom, array $overrides = []): array
    {
        return array_merge([
            'classroom_id' => $classroom->id,
            'class_name' => '一年甲班',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => '班級活動',
        ], $overrides);
    }

    public function test_user_can_view_reservation_page_and_create_form(): void
    {
        $this->get(route('reservations.index'))->assertOk();
        $this->get(route('reservations.create'))->assertOk();
    }


    public function test_create_form_only_shows_active_normal_classrooms(): void
    {
        $normal = $this->makeClassroom('A101');

        $abnormal = $this->makeClassroom('A102', [
            'reservation_status' => 'abnormal',
        ]);

        $inactive = $this->makeClassroom('A103', [
            'is_active' => false,
        ]);

        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee($normal->name)
            ->assertDontSee($abnormal->name)
            ->assertDontSee($inactive->name);
    }


    public function test_user_can_create_a_pending_reservation(): void
    {
        $classroom = $this->makeClassroom();

        $this->post(route('reservations.store'), $this->payload($classroom))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classroom_reservations', [
            'classroom_id' => $classroom->id,
            'requester_id' => $this->applicant->id,
            'class_name' => '一年甲班',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_only_see_their_own_reservations(): void
    {
        $classroom = $this->makeClassroom();

        $own = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $otherUser = $this->makeUserWithRole('teacher', '教師');
        ClassroomReservation::create([
            ...$this->payload($classroom, [
                'class_name' => '二年乙班',
                'start_time' => '10:00',
                'end_time' => '11:00',
            ]),
            'requester_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        $this->get(route('reservations.index'))
            ->assertOk()
            ->assertSee($own->class_name)
            ->assertDontSee('二年乙班');
    }

    public function test_overlapping_pending_reservations_are_allowed_after_confirmation(): void
    {
        $classroom = $this->makeClassroom();

        $this->post(route('reservations.store'), $this->payload($classroom))
            ->assertSessionHas('success');

        $overlappingPayload = $this->payload($classroom, [
            'class_name' => '二年乙班',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'confirm_pending_conflict' => '1',
        ]);

        // 使用者完成原本的確認流程後，可提交重疊的待審核申請。
        $this->post(route('reservations.store'), $overlappingPayload)
            ->assertSessionHas('success');

        $this->assertDatabaseCount('classroom_reservations', 2);
        $this->assertDatabaseHas('classroom_reservations', [
            'classroom_id' => $classroom->id,
            'class_name' => '二年乙班',
            'status' => 'pending',
        ]);
    }

    public function test_approved_reservation_overlap_cannot_be_bypassed_by_confirmation(): void
    {
        $classroom = $this->makeClassroom();

        ClassroomReservation::create([
            ...$this->payload($classroom, [
                'class_name' => '已核准班級',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'approved',
        ]);

        // 即使傳入確認欄位，仍不得與已核准預約重疊。
        $this->post(route('reservations.store'), $this->payload($classroom, [
            'class_name' => '二年乙班',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'confirm_pending_conflict' => '1',
        ]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('classroom_reservations', 1);
    }

    public function test_inactive_or_abnormal_classroom_cannot_be_reserved(): void
    {
        $inactive = $this->makeClassroom('A102', ['is_active' => false]);

        $this->post(route('reservations.store'), $this->payload($inactive))
            ->assertRedirect()
            ->assertSessionHas('error');

        $abnormal = $this->makeClassroom('A103', ['reservation_status' => 'abnormal']);

        $this->post(route('reservations.store'), $this->payload($abnormal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('classroom_reservations', 0);
    }

    public function test_end_time_must_be_later_than_start_time(): void
    {
        $classroom = $this->makeClassroom();

        $this->from(route('reservations.create'))
            ->post(route('reservations.store'), $this->payload($classroom, [
                'start_time' => '10:00',
                'end_time' => '09:00',
            ]))
            ->assertRedirect(route('reservations.create'))
            ->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('classroom_reservations', 0);
    }


     public function test_manager_can_approve_pending_reservation_and_audit_it(): void
    {
        $classroom = $this->makeClassroom();
        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $manager = $this->loginAsAnyUser();

        $this->post(route('reservations.approve', $reservation))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
            'rejection_reason' => null,
        ]);

        $this->assertNotNull($reservation->fresh()->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'status_changed',
            'loggable_id' => $reservation->id,
            'loggable_type' => $reservation->getMorphClass(),
        ]);
    }

    public function test_manager_can_reject_pending_reservation_with_reason(): void
    {
        $classroom = $this->makeClassroom();
        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $manager = $this->loginAsAnyUser();

        $this->post(route('reservations.reject', $reservation), [
            'rejection_reason' => '該時段需進行設備維護。',
        ])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'rejected',
            'reviewed_by' => $manager->id,
            'rejection_reason' => '該時段需進行設備維護。',
        ]);

        $this->assertNotNull($reservation->fresh()->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'status_changed',
            'loggable_id' => $reservation->id,
            'loggable_type' => $reservation->getMorphClass(),
        ]);
    }

    public function test_user_without_reservation_permission_cannot_review(): void
    {
        $classroom = $this->makeClassroom();
        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->applicant)
            ->post(route('reservations.approve', $reservation))
            ->assertForbidden();

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_already_reviewed_reservation_cannot_be_approved_again(): void
    {
        $classroom = $this->makeClassroom();
        $manager = $this->loginAsAnyUser();

        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        $this->post(route('reservations.approve', $reservation))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'approved',
        ]);
    }

    public function test_approval_is_rejected_when_another_approved_reservation_overlaps(): void
    {
        $classroom = $this->makeClassroom();
        $manager = $this->loginAsAnyUser();

        ClassroomReservation::create([
            ...$this->payload($classroom, [
                'class_name' => '已核准班級',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        $pending = ClassroomReservation::create([
            ...$this->payload($classroom, [
                'class_name' => '待審核班級',
                'start_time' => '09:30',
                'end_time' => '10:30',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $this->post(route('reservations.approve', $pending))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $pending->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_approval_is_rejected_when_a_later_date_slot_overlaps(): void
    {
        $classroom = $this->makeClassroom();
        $manager = $this->loginAsAnyUser();

        $firstDate = now()->addDays(5)->toDateString();
        $secondDate = now()->addDays(6)->toDateString();

        // 第二個日期已有一筆核准預約。
        $approved = ClassroomReservation::create([
            ...$this->payload($classroom, [
                'reservation_date' => $secondDate,
                'class_name' => '已核准班級',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'approved',
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        $approved->slots()->create([
            'reservation_date' => $secondDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'period_no' => null,
        ]);

        // 待核准預約有兩個日期：第一天沒有衝突，第二天才有衝突。
        $pending = ClassroomReservation::create([
            ...$this->payload($classroom, [
                'reservation_date' => $firstDate,
                'class_name' => '跨日期待審核班級',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $pending->slots()->createMany([
            [
                'reservation_date' => $firstDate,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'period_no' => 1,
            ],
            [
                'reservation_date' => $secondDate,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'period_no' => 1,
            ],
        ]);

        $this->post(route('reservations.approve', $pending))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $pending->id,
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }
    public function test_approval_is_rejected_when_classroom_becomes_abnormal(): void
    {
        $classroom = $this->makeClassroom();

        // 教室正常時，先建立待審核預約。
        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        // 模擬預約建立後，教室因設備異常而暫停外借。
        $classroom->update([
            'reservation_status' => 'abnormal',
        ]);

        // 管理人員嘗試核准，應該被拒絕。
        $this->loginAsAnyUser();

        $this->post(route('reservations.approve', $reservation))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');

        // 預約仍須維持待審核，不得被核准。
        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_rejection_reason_is_required(): void
    {
        $classroom = $this->makeClassroom();
        $this->loginAsAnyUser();

        $reservation = ClassroomReservation::create([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $this->from(route('reservations.index'))
            ->post(route('reservations.reject', $reservation), [
                'rejection_reason' => '',
            ])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHasErrors('rejection_reason');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_timetable_endpoint_returns_demo_periods_and_courses(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $date = now()->next('Monday')->toDateString();

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $classroom->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('classroom.id', $classroom->id)
            ->assertJsonPath('date', $date)
            ->assertJsonPath('periods.0.period_no', 1)
            ->assertJsonPath('periods.0.start_time', '08:10')
            ->assertJsonPath('periods.0.end_time', '09:00')
            ->assertJsonPath('periods.0.is_class', true)
            ->assertJsonPath('periods.0.class_name', '資一忠')
            ->assertJsonPath('periods.0.course_name', '資訊科技');
    }

    public function test_timetable_endpoint_includes_pending_reservations(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $date = now()->next('Monday')->toDateString();

        ClassroomReservation::create([
            ...$this->payload($classroom, [
                'reservation_date' => $date,
                'class_name' => '測試預約班級',
                'start_time' => '08:20',
                'end_time' => '08:40',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $classroom->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath('periods.0.reservations.0.class_name', '測試預約班級')
            ->assertJsonPath('periods.0.reservations.0.status', 'pending')
            ->assertJsonPath('periods.0.reservations.0.start_time', '08:20')
            ->assertJsonPath('periods.0.reservations.0.end_time', '08:40');
    }

    public function test_timetable_endpoint_rejects_inactive_or_abnormal_classrooms(): void
    {
        $inactive = $this->makeClassroom('A102', [
            'is_active' => false,
        ]);

        $abnormal = $this->makeClassroom('A103', [
            'reservation_status' => 'abnormal',
        ]);

        $date = now()->addDays(3)->toDateString();

        foreach ([$inactive, $abnormal] as $classroom) {
            $this->getJson(route('reservations.timetable', [
                'classroom_id' => $classroom->id,
                'date' => $date,
            ]))
                ->assertStatus(422);
        }
    }

    public function test_timetable_endpoint_rejects_past_dates(): void
    {
        $classroom = $this->makeClassroom('IT-301');

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $classroom->id,
            'date' => now()->subDay()->toDateString(),
        ]))
            ->assertStatus(422);
    }

}