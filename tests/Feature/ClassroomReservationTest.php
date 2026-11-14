<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\Concerns\CreatesReservations;
use Tests\TestCase;

class ClassroomReservationTest extends TestCase
{
    use CreatesReservations;
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

    /** 沒有審核權限的人：選單的「空間申請」就是有課表的申請頁，下方是「我的申請」；看不到別人的申請，也沒有右上角的兩個按鈕。 */
    public function test_user_without_review_permission_sees_timetable_and_own_requests(): void
    {
        $classroom = $this->makeClassroom();
        $other = $this->makeUserWithRole('teacher2', '教師二');
        $this->makeReservation([...$this->payload($classroom, ['class_name' => '別人的班級']), 'requester_id' => $other->id, 'status' => 'pending']);
        $this->makeReservation([...$this->payload($classroom, ['class_name' => '我的班級']), 'requester_id' => $this->applicant->id, 'status' => 'pending']);

        $this->get(route('reservations.index'))
            ->assertOk()
            ->assertSee('id="my-requests"', false)
            ->assertSee('我的申請')
            ->assertDontSee(route('reservations.mine'), false)
            ->assertSee('我的班級')
            ->assertDontSee('別人的班級');
        $this->get(route('reservations.create'))->assertOk()->assertSee('id="my-requests"', false);
    }

    /** 有審核權限的人：空間申請是全部申請列表，右上角「空間預約」換到有課表的頁面、「我的申請」在對話框打開；有課表的頁面不再顯示「我的申請」。 */
    public function test_reviewer_sees_request_list_with_reservation_and_mine_buttons(): void
    {
        $classroom = $this->makeClassroom();
        $reviewer = $this->loginAsAnyUser();
        $this->makeReservation([...$this->payload($classroom, ['class_name' => '審核者的班級']), 'requester_id' => $reviewer->id, 'status' => 'pending']);

        $this->actingAs($reviewer)->get(route('reservations.index'))
            ->assertOk()
            ->assertSee('href="' . route('reservations.create') . '"', false)
            ->assertSee('href="' . route('reservations.mine') . '" class="tb-btn tb-btn-outline" data-modal-frame data-modal-table data-modal-title="我的申請"', false);
        $this->actingAs($reviewer)->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('空間預約')
            ->assertDontSee('id="my-requests"', false);
        $this->actingAs($reviewer)->get(route('reservations.mine', ['modal' => 1]))
            ->assertOk()
            ->assertSee('審核者的班級')
            ->assertSee('tb-table-wrap', false);
    }


    /** 啟用中的教室都可以選（暫停預約的教室也能選來查課表，送出時才擋）；停用的教室不出現。 */
    public function test_create_form_shows_every_active_classroom(): void
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
            ->assertSee('data-id="' . $normal->id . '"', false)
            ->assertSee('data-id="' . $abnormal->id . '"', false)
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

    public function test_my_requests_only_lists_the_users_own_reservations(): void
    {
        $classroom = $this->makeClassroom();

        $own = $this->makeReservation([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $otherUser = $this->makeUserWithRole('teacher', '教師');
        $this->makeReservation([
            ...$this->payload($classroom, [
                'class_name' => '二年乙班',
                'start_time' => '10:00',
                'end_time' => '11:00',
            ]),
            'requester_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        // 「我的申請」只列自己提出的申請。
        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('href="' . route('reservations.show', $own) . '"', false)
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

        $this->makeReservation([
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
        $reservation = $this->makeReservation([
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
        $reservation = $this->makeReservation([
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
        $reservation = $this->makeReservation([
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

        $reservation = $this->makeReservation([
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

    public function test_request_overlapping_an_approved_reservation_is_rejected_automatically(): void
    {
        $classroom = $this->makeClassroom();
        $manager = $this->loginAsAnyUser();

        $this->makeReservation([
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

        $pending = $this->makeReservation([
            ...$this->payload($classroom, [
                'class_name' => '待審核班級',
                'start_time' => '09:30',
                'end_time' => '10:30',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        // 和已核准的時段重疊：這一節不能同意。
        $this->post(route('reservations.approve', $pending))
            ->assertSessionHas('error');
        $this->assertSame('pending', $pending->fresh()->status);

        // 打開空間申請列表時，重疊的那一節自動拒絕；這筆只有這一節，所以整筆變成已拒絕（審核人是系統）。
        $this->get(route('reservations.index'))->assertOk();
        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $pending->id,
            'status' => 'rejected',
            'reviewed_by' => null,
        ]);
    }

    public function test_request_whose_later_date_overlaps_keeps_the_free_date_pending(): void
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
            'period_number' => null,
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
                'period_number' => 1,
            ],
            [
                'reservation_date' => $secondDate,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'period_number' => 1,
            ],
        ]);

        $this->post(route('reservations.approve', $pending))
            ->assertSessionHas('error');

        // 逐節審核：只有重疊的第二天自動拒絕，第一天仍待審核、可以同意。
        $this->get(route('reservations.index'))->assertOk();
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame([null, 'rejected'], $pending->fresh('slots')->slots->pluck('status')->all());
    }
    public function test_approval_is_rejected_when_classroom_becomes_abnormal(): void
    {
        $classroom = $this->makeClassroom();

        // 教室正常時，先建立待審核預約。
        $reservation = $this->makeReservation([
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

    public function test_rejection_reason_is_optional(): void
    {
        $classroom = $this->makeClassroom();
        $this->loginAsAnyUser();

        $reservation = $this->makeReservation([
            ...$this->payload($classroom),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $this->post(route('reservations.reject', $reservation), ['rejection_reason' => ''])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success', '預約已拒絕。');

        $this->assertDatabaseHas('classroom_reservations', [
            'id' => $reservation->id,
            'status' => 'rejected',
            'rejection_reason' => null,
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
            // 第一列是早自習，接著第1節
            ->assertJsonPath('periods.0.label', '早自習')
            ->assertJsonPath('periods.0.period_number', 0)
            ->assertJsonPath('periods.0.start_time', '08:00')
            ->assertJsonPath('periods.1.period_number', 1)
            ->assertJsonPath('periods.1.label', '第1節')
            ->assertJsonPath('periods.1.start_time', '08:30')
            ->assertJsonPath('periods.1.end_time', '09:20')
            ->assertJsonPath('periods.1.is_class', true)
            ->assertJsonPath('periods.1.class_name', '資一忠')
            ->assertJsonPath('periods.1.course_name', '資訊科技')
            ->assertJsonPath('periods.5.label', '午休')
            ->assertJsonCount(15, 'periods');
    }

    public function test_timetable_endpoint_includes_pending_reservations(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $date = now()->next('Monday')->toDateString();

        $this->makeReservation([
            ...$this->payload($classroom, [
                'reservation_date' => $date,
                'class_name' => '測試預約班級',
                'start_time' => '08:20',
                'end_time' => '08:40',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        // 一般使用者（不是申請人）看不到是哪一班預約的；申請人自己與有審核權限的人看得到。
        $this->getJson(route('reservations.timetable', ['classroom_id' => $classroom->id, 'date' => $date]))
            ->assertOk()->assertJsonPath('periods.1.reservations.0.class_name', '測試預約班級')->assertJsonPath('periods.1.reservations.0.mine', true);
        $this->actingAs($this->makeUserWithRole('student', '學生'));
        $this->getJson(route('reservations.timetable', ['classroom_id' => $classroom->id, 'date' => $date]))
            ->assertOk()->assertJsonMissingPath('periods.1.reservations.0.class_name')->assertJsonPath('periods.1.reservations.0.status', 'pending');
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $classroom->id,
            'date' => $date,
        ]))
            ->assertOk()
            // 08:20–08:40 落在第1節（08:30–09:20），第1節是 periods.1（periods.0 是早自習）
            ->assertJsonPath('periods.1.reservations.0.class_name', '測試預約班級')
            ->assertJsonPath('periods.1.reservations.0.status', 'pending')
            ->assertJsonPath('periods.1.reservations.0.start_time', '08:20')
            ->assertJsonPath('periods.1.reservations.0.end_time', '08:40');
    }

    /** 停用的教室查不到課表；暫停預約（設備異常）的教室仍可查詢課表。 */
    public function test_timetable_endpoint_rejects_inactive_but_allows_abnormal_classrooms(): void
    {
        $inactive = $this->makeClassroom('A102', [
            'is_active' => false,
        ]);

        $abnormal = $this->makeClassroom('A103', [
            'reservation_status' => 'abnormal',
        ]);

        $date = now()->addDays(3)->toDateString();

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $inactive->id,
            'date' => $date,
        ]))
            ->assertStatus(422);

        $this->getJson(route('reservations.timetable', [
            'classroom_id' => $abnormal->id,
            'date' => $date,
        ]))
            ->assertOk();
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

    public function test_timetable_week_endpoint_returns_monday_to_sunday(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $selectedDate = now()->addWeeks(2)->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(2);
        $monday = $selectedDate->copy()->startOfWeek(\Carbon\Carbon::MONDAY);

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $classroom->id,
            'date' => $selectedDate->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('classroom.id', $classroom->id)
            ->assertJsonPath('selected_date', $selectedDate->toDateString())
            ->assertJsonPath('week_start', $monday->toDateString())
            ->assertJsonPath('week_end', $monday->copy()->addDays(6)->toDateString())
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.date', $monday->toDateString())
            ->assertJsonPath('days.6.date', $monday->copy()->addDays(6)->toDateString())
            ->assertJsonPath('days.0.periods.1.period_number', 1)
            ->assertJsonPath('days.0.periods.1.start_time', '08:30');
    }

    public function test_timetable_week_endpoint_includes_pending_and_approved_slots(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $monday = now()->addWeeks(2)->startOfWeek(\Carbon\Carbon::MONDAY);
        $tuesday = $monday->copy()->addDay();

        $pending = ClassroomReservation::create([
            ...$this->payload($classroom, [
                'reservation_date' => $monday->toDateString(),
                'class_name' => '待審核週課表班級',
                'start_time' => '08:20',
                'end_time' => '08:40',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        \App\Models\ClassroomReservationSlot::create([
            'classroom_reservation_id' => $pending->id,
            'reservation_date' => $monday->toDateString(),
            'start_time' => '08:20',
            'end_time' => '08:40',
            'period_number' => 1,
        ]);

        $approved = ClassroomReservation::create([
            ...$this->payload($classroom, [
                'reservation_date' => $tuesday->toDateString(),
                'class_name' => '已核准週課表班級',
                'start_time' => '09:10',
                'end_time' => '09:30',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'approved',
        ]);

        \App\Models\ClassroomReservationSlot::create([
            'classroom_reservation_id' => $approved->id,
            'reservation_date' => $tuesday->toDateString(),
            'start_time' => '09:10',
            'end_time' => '09:30',
            'period_number' => 2,
        ]);

        // 班級名稱只給有審核權限的人看（課表上顯示「班級＋已借用」）。
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $classroom->id,
            'date' => $monday->copy()->addDays(2)->toDateString(),
        ]))
            ->assertOk()
            // periods.0 是早自習；08:20–08:40 在第1節（periods.1），09:10–09:30 也落在第1節（08:30–09:20）
            ->assertJsonPath('days.0.periods.1.reservations.0.class_name', '待審核週課表班級')
            ->assertJsonPath('days.0.periods.1.reservations.0.status', 'pending')
            ->assertJsonPath('days.1.periods.1.reservations.0.class_name', '已核准週課表班級')
            ->assertJsonPath('days.1.periods.1.reservations.0.status', 'approved');
    }

    public function test_timetable_week_endpoint_excludes_reservations_outside_the_week(): void
    {
        $classroom = $this->makeClassroom('IT-301');
        $monday = now()->addWeeks(2)->startOfWeek(\Carbon\Carbon::MONDAY);
        $outsideDate = $monday->copy()->addDays(7);   // 下一週的星期一（週課表現在包含星期六、日）

        $this->makeReservation([
            ...$this->payload($classroom, [
                'reservation_date' => $outsideDate->toDateString(),
                'class_name' => '下週預約不應出現',
                'start_time' => '08:20',
                'end_time' => '08:40',
            ]),
            'requester_id' => $this->applicant->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $classroom->id,
            'date' => $monday->toDateString(),
        ]));

        $response->assertOk()->assertJsonCount(7, 'days');

        foreach ($response->json('days') as $day) {
            foreach ($day['periods'] as $period) {
                foreach ($period['reservations'] as $reservation) {
                    $this->assertNotSame(
                        '下週預約不應出現',
                        $reservation['class_name']
                    );
                }
            }
        }
    }

    public function test_timetable_week_endpoint_rejects_invalid_parameters(): void
    {
        $classroom = $this->makeClassroom('IT-301');

        $this->getJson(route('reservations.timetable.week'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classroom_id', 'date']);

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => 999999,
            'date' => now()->addDays(3)->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classroom_id']);

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $classroom->id,
            'date' => 'not-a-date',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $classroom->id,
            'date' => now()->subDay()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_timetable_week_endpoint_rejects_inactive_but_allows_abnormal_classrooms(): void
    {
        $inactive = $this->makeClassroom('W101', [
            'is_active' => false,
        ]);

        $abnormal = $this->makeClassroom('W102', [
            'reservation_status' => 'abnormal',
        ]);

        $date = now()->addWeeks(2)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $inactive->id,
            'date' => $date,
        ]))->assertStatus(422);

        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $abnormal->id,
            'date' => $date,
        ]))->assertOk();
    }

    /**
     * 跨日一次申請多個時段：第一天選下午、第二天選第一節。
     * 申請本身的開始／結束取最早的那一節，不能拿第一天下午的開始配第二天早上的結束（以前會被誤判成結束早於開始）。
     */
    public function test_slots_across_two_days_can_be_submitted_together(): void
    {
        $classroom = $this->makeClassroom();
        $day1 = now()->addDays(15)->toDateString();
        $day2 = now()->addDays(16)->toDateString();

        $this->post(route('reservations.store'), [
            'classroom_id' => $classroom->id,
            'class_name' => '一年甲班',
            'purpose' => '跨日活動',
            // 畫面舊版送出的代表時間：第一天的開始＋第二天的結束
            'reservation_date' => $day1,
            'start_time' => '13:10',
            'end_time' => '09:00',
            'slots' => [
                ['reservation_date' => $day1, 'start_time' => '13:10', 'end_time' => '14:00', 'period_number' => 5],
                ['reservation_date' => $day2, 'start_time' => '08:10', 'end_time' => '09:00', 'period_number' => 1],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        // 使用時間只存在時段明細：兩個時段都在，最早的是第一天下午那一個。
        $reservation = ClassroomReservation::query()->sole();
        $this->assertSame(2, $reservation->slots()->count());
        $first = $reservation->firstSlot;
        $this->assertSame($day1, $first->reservation_date->toDateString());
        $this->assertSame('13:10', substr((string) $first->start_time, 0, 5));
        $this->assertSame('14:00', substr((string) $first->end_time, 0, 5));
    }

    /** 今天已經開始的節次不能預約；今天稍晚的節次可以。 */
    public function test_a_period_that_has_already_started_today_cannot_be_reserved(): void
    {
        $classroom = $this->makeClassroom();
        $this->travelTo(now()->setTime(13, 0));
        $today = now()->toDateString();

        $this->post(route('reservations.store'), $this->payload($classroom, [
            'reservation_date' => $today, 'start_time' => '10:10', 'end_time' => '11:00',
        ]))->assertSessionHas('error');
        $this->assertDatabaseCount('classroom_reservations', 0);

        $this->post(route('reservations.store'), $this->payload($classroom, [
            'reservation_date' => $today, 'start_time' => '13:10', 'end_time' => '14:00',
        ]))->assertSessionHas('success');
    }

    /** 送出申請時可以勾選「其中一節無法借用就整筆不要」；表單上看得到這個選項。 */
    public function test_requester_can_choose_all_or_nothing_when_submitting(): void
    {
        $classroom = $this->makeClassroom();

        $this->get(route('reservations.create'))->assertSee('其中一節無法借用時，整筆申請都不要');

        $this->post(route('reservations.store'), $this->payload($classroom, ['class_name' => '整筆班', 'is_all_or_nothing' => '1']))->assertSessionHasNoErrors();
        $this->post(route('reservations.store'), $this->payload($classroom, ['class_name' => '逐節班', 'start_time' => '13:00', 'end_time' => '14:00']))->assertSessionHasNoErrors();

        $this->assertTrue(ClassroomReservation::where('class_name', '整筆班')->firstOrFail()->is_all_or_nothing);
        $this->assertFalse(ClassroomReservation::where('class_name', '逐節班')->firstOrFail()->is_all_or_nothing);
    }

    // 教師、學生（沒有審核權限）在課表上要認得出「哪一格是自己的申請」：自己的保留班級名稱並標示 mine，
    // 別人的仍然只有「有班申請待審核／已借用」，不透露是哪一班、也不透露申請人。
    public function test_timetable_marks_my_own_reservations_and_keeps_other_classes_anonymous(): void
    {
        $classroom = $this->makeClassroom('IT-401');
        $date = now()->addDays(3)->toDateString();
        $other = $this->makeUserWithRole('student', '學生');

        $mine = ClassroomReservation::create([...$this->payload($classroom, ['reservation_date' => $date, 'class_name' => '我自己的班']), 'requester_id' => $this->applicant->id, 'status' => 'pending']);
        $theirs = ClassroomReservation::create([...$this->payload($classroom, ['reservation_date' => $date, 'class_name' => '別人的班']), 'requester_id' => $other->id, 'status' => 'pending']);
        foreach ([$mine, $theirs] as $reservation) {
            \App\Models\ClassroomReservationSlot::create(['classroom_reservation_id' => $reservation->id, 'reservation_date' => $date, 'start_time' => '09:10', 'end_time' => '09:30', 'period_number' => 2]);
        }

        $response = $this->getJson(route('reservations.timetable', ['classroom_id' => $classroom->id, 'date' => $date]))->assertOk();
        $entries = collect($response->json('periods'))->pluck('reservations')->flatten(1)->values();

        $this->assertCount(2, $entries);
        $own = $entries->firstWhere('mine', true);
        $this->assertSame('我自己的班', $own['class_name']);                       // 自己的：看得到班級
        $foreign = $entries->firstWhere('mine', false);
        $this->assertArrayNotHasKey('class_name', $foreign);                         // 別人的：班級不透露
        $this->assertStringNotContainsString('別人的班', json_encode($response->json(), JSON_UNESCAPED_UNICODE));
        $this->assertArrayNotHasKey('requester_id', $own);                           // 也不回傳申請人編號

        // 週課表同樣標示 mine。
        $week = $this->getJson(route('reservations.timetable.week', ['classroom_id' => $classroom->id, 'date' => $date]))->assertOk();
        $weekText = json_encode($week->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('我自己的班', $weekText);
        $this->assertStringNotContainsString('別人的班', $weekText);

        // 另一個人（學生）看同一天，自己的才有班級；老師那筆對他來說是匿名的。
        $this->actingAs($other);
        $asStudent = json_encode($this->getJson(route('reservations.timetable', ['classroom_id' => $classroom->id, 'date' => $date]))->assertOk()->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('別人的班', $asStudent);
        $this->assertStringNotContainsString('我自己的班', $asStudent);
    }

    // 送出空間預約申請後，不論有沒有審核權限，都回到「空間申請」主畫面，不停留在送出申請的畫面。
    public function test_after_submitting_a_request_everyone_goes_back_to_the_main_reservation_screen(): void
    {
        $classroom = $this->makeClassroom('A301');

        $this->post(route('reservations.store'), $this->payload($classroom))->assertRedirect(route('reservations.index'));

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));
        $this->post(route('reservations.store'), $this->payload($classroom, ['start_time' => '11:00', 'end_time' => '12:00']))
            ->assertRedirect(route('reservations.index'));
        // 有審核權限的人回到的是全部申請的列表（不是送出申請的表單頁）。
        $this->followingRedirects()->get(route('reservations.index'))->assertOk()->assertSee('全部申請');
    }
}
