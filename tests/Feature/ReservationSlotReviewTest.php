<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 空間預約：列表只顯示申請時間／申請人／申請班級／已審核或未審核，詳細頁可逐一審核每個時段。
 */
class ReservationSlotReviewTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $applicant;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicant = $this->makeUserWithRole('teacher', '教師', ['name' => '林老師']);
        $this->classroom = Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室',
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    /** 建立一筆有多個時段的待審核申請；$times 是 [日期偏移天數, 開始, 結束]。 */
    private function request(array $times, array $attrs = []): ClassroomReservation
    {
        $first = $times[0];
        $reservation = ClassroomReservation::create($attrs + [
            'classroom_id' => $this->classroom->id,
            'requester_id' => $this->applicant->id,
            'class_name' => '一年甲班',
            'reservation_date' => now()->addDays($first[0])->toDateString(),
            'start_time' => $first[1],
            'end_time' => $first[2],
            'purpose' => '班級活動',
            'status' => 'pending',
        ]);

        foreach ($times as [$day, $start, $end]) {
            $reservation->slots()->create(['reservation_date' => now()->addDays($day)->toDateString(), 'start_time' => $start, 'end_time' => $end]);
        }

        return $reservation->fresh('slots');
    }

    public function test_list_shows_request_time_requester_class_and_reviewed_state_and_links_to_the_detail_page(): void
    {
        $pending = $this->request([[3, '09:00', '10:00']]);
        $done = $this->request([[4, '09:00', '10:00']], ['class_name' => '二年乙班', 'status' => 'approved', 'reviewed_at' => now()]);

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertSeeInOrder(['申請時間', '申請人', '申請班級', '申請狀態'])
            ->assertSee('林老師')
            ->assertSee('一年甲班')
            ->assertSee('未審核')
            ->assertSee('已審核')
            ->assertSee('data-href="' . route('reservations.show', $pending) . '"', false)
            ->assertDontSee('審核時間');
    }

    public function test_detail_page_is_visible_to_the_requester_and_managers_only(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->applicant)->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('班級活動')->assertSee('09:00–10:00')->assertDontSee('送出審核');

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('reservations.show', $reservation))->assertForbidden();

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('送出審核')->assertSee('全部同意');
    }

    public function test_manager_can_approve_some_periods_and_reject_the_rest_with_a_reason(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [3, '10:10', '11:00'], [4, '09:00', '10:00']]);
        [$a, $b, $c] = $reservation->slots;
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        $this->actingAs($manager)->post(route('reservations.review', $reservation), ['decisions' => [
            $a->id => ['decision' => 'approved'],
            $b->id => ['decision' => 'approved'],
            $c->id => ['decision' => 'rejected', 'reason' => '當天教室要整修'],
        ]])->assertRedirect(route('reservations.show', $reservation))->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame('partially_approved', $reservation->status);
        $this->assertSame($manager->id, $reservation->reviewed_by);
        $this->assertNotNull($reservation->reviewed_at);
        $this->assertSame(['approved', 'approved', 'rejected'], $reservation->slots->pluck('status')->all());
        $this->assertSame('當天教室要整修', $reservation->slots[2]->rejection_reason);

        // 詳細頁顯示審核人與每個時段的結果。
        $this->get(route('reservations.show', $reservation))
            ->assertSee('部分核准')->assertSee($manager->name)->assertSee('當天教室要整修');
    }

    public function test_every_period_must_be_decided_and_rejections_need_a_reason(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [3, '10:10', '11:00']]);
        [$a, $b] = $reservation->slots;
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        // 只同意一個、另一個沒選 → 不能送出。
        $this->post(route('reservations.review', $reservation), ['decisions' => [$a->id => ['decision' => 'approved']]])
            ->assertSessionHas('error');

        // 拒絕但沒寫原因 → 不能送出。
        $this->post(route('reservations.review', $reservation), ['decisions' => [
            $a->id => ['decision' => 'approved'],
            $b->id => ['decision' => 'rejected', 'reason' => ''],
        ]])->assertSessionHasErrors("decisions.{$b->id}.reason");

        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertSame([null, null], $reservation->fresh('slots')->slots->pluck('status')->all());
    }

    public function test_an_approved_period_that_conflicts_is_refused_but_can_be_rejected_instead(): void
    {
        // 別人已核准第 3 天 09:00–10:00。
        $this->request([[3, '09:00', '10:00']], ['class_name' => '已核准班級', 'status' => 'approved']);
        $reservation = $this->request([[3, '09:30', '10:30'], [4, '09:00', '10:00']]);
        [$clash, $free] = $reservation->slots;
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->post(route('reservations.review', $reservation), ['decisions' => [
            $clash->id => ['decision' => 'approved'],
            $free->id => ['decision' => 'approved'],
        ]])->assertSessionHas('error');
        $this->assertSame('pending', $reservation->fresh()->status);

        $this->post(route('reservations.review', $reservation), ['decisions' => [
            $clash->id => ['decision' => 'rejected', 'reason' => '已有其他班級借用'],
            $free->id => ['decision' => 'approved'],
        ]])->assertSessionHas('success');
        $this->assertSame('partially_approved', $reservation->fresh()->status);
    }

    public function test_only_the_approved_periods_of_a_partial_approval_block_new_requests(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [3, '13:00', '14:00']]);
        [$approved, $rejected] = $reservation->slots;
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))
            ->post(route('reservations.review', $reservation), ['decisions' => [
                $approved->id => ['decision' => 'approved'],
                $rejected->id => ['decision' => 'rejected', 'reason' => '下午有考試'],
            ]]);

        $this->actingAs($this->applicant);
        $date = now()->addDays(3)->toDateString();
        $base = ['classroom_id' => $this->classroom->id, 'class_name' => '三年丙班', 'reservation_date' => $date, 'purpose' => '社團'];

        $this->post(route('reservations.store'), $base + ['start_time' => '09:30', 'end_time' => '10:30'])->assertSessionHas('error');
        $this->post(route('reservations.store'), $base + ['start_time' => '13:00', 'end_time' => '14:00'])->assertSessionHas('success');
    }

    public function test_requester_cannot_review_their_own_request(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->applicant)
            ->post(route('reservations.review', $reservation), ['decisions' => [$reservation->slots[0]->id => ['decision' => 'approved']]])
            ->assertForbidden();
    }
}
