<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 空間預約：列表只顯示送出時間／申請人／申請班級／已審核或未審核；詳細頁逐節審核（同意／拒絕）。
 * 同意一節後，其他申請裡重疊的那一節自動拒絕（申請人選了整筆的，整筆自動拒絕）；
 * 時間過了還沒審核的申請維持待審核、之後仍可補同意；已拒絕與逾期未審核的申請由 reservations:cleanup 清除。
 */
class ReservationReviewTest extends TestCase
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
            ->assertSeeInOrder(['申請班級', '申請人', '送出時間', '申請狀態'])
            ->assertSee('林老師')
            ->assertSee('一年甲班')
            ->assertSee('未審核')
            ->assertSee('已審核')
            ->assertSee('data-modal-frame-href="' . route('reservations.show', $pending) . '" data-modal-title="預約詳細"', false)
            ->assertDontSee('審核時間');
    }

    public function test_detail_page_is_visible_to_the_requester_and_managers_only(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->applicant)->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('班級活動')->assertDontSee('拒絕原因（選填）');

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('reservations.show', $reservation))->assertForbidden();

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('拒絕原因（選填')->assertSee('>送出審核</button>', false)->assertSee('>同意</label>', false)->assertSee('>拒絕</label>', false);
    }

    public function test_detail_shows_fields_in_order_with_date_and_period_only(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);
        $reservation->slots[0]->update(['start_time' => '08:10', 'end_time' => '09:00', 'period_number' => 1]);
        $date = now()->addDays(3);
        $weekday = ['日', '一', '二', '三', '四', '五', '六'][$date->dayOfWeek];

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))
            ->get(route('reservations.show', ['reservation' => $reservation, 'modal' => 1]))
            ->assertOk()
            ->assertSeeInOrder(['申請狀態', '送出時間', '教室', '申請班級', '申請人', '申請時段', '用途', '拒絕原因（選填'])
            ->assertDontSee('借用方式')         // 沒勾選「其中一節無法借用就整筆不要」就不顯示借用方式
            ->assertSee($date->format('Y-m-d') . '（' . $weekday . '）第 1 節')
            ->assertDontSee('08:10')            // 時間只記在資料裡，不顯示
            ->assertDontSee('教室設備報修中')     // 沒有設備在報修就不顯示
            ->assertDontSee('<h1', false);      // 在對話框裡不顯示頁首（標題由對話框顯示「預約詳細」）
    }

    public function test_manager_approves_the_whole_request(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [4, '09:00', '10:00']]);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        $this->actingAs($manager)->post(route('reservations.approve', $reservation))
            ->assertRedirect(route('reservations.index'))->assertSessionHas('success', '預約已核准。');

        $reservation->refresh();
        $this->assertSame('approved', $reservation->status);
        $this->assertSame($manager->id, $reservation->reviewed_by);
        $this->assertSame(['approved', 'approved'], $reservation->slots->pluck('status')->all());
    }

    public function test_reviewing_inside_the_popup_closes_it_and_returns_to_the_list(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))
            ->post(route('reservations.reject', $reservation), ['rejection_reason' => '', 'modal' => 1])
            ->assertOk()->assertViewIs('layouts.modal-done')->assertViewHas('url', route('reservations.index'));
        $this->assertSame('rejected', $reservation->fresh()->status);
    }

    public function test_manager_can_approve_some_periods_and_reject_others(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [3, '13:00', '14:00']]);
        [$morning, $afternoon] = $reservation->slots;
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        $this->actingAs($manager)->post(route('reservations.review', $reservation), [
            'decisions' => [$morning->id => 'approved', $afternoon->id => 'rejected'],
            'rejection_reason' => '下午有考試',
        ])->assertRedirect(route('reservations.index'))->assertSessionHas('success', '預約已部分核准。');

        $reservation->refresh();
        $this->assertSame('partially_approved', $reservation->status);
        $this->assertSame($manager->id, $reservation->reviewed_by);
        $this->assertSame(['approved', 'rejected'], $reservation->slots->pluck('status')->all());
        $this->assertSame('下午有考試', $reservation->slots[1]->rejection_reason);

        // 每一節都要選。
        $other = $this->request([[5, '09:00', '10:00'], [5, '13:00', '14:00']]);
        $this->post(route('reservations.review', $other), ['decisions' => [$other->slots[0]->id => 'approved']])
            ->assertSessionHas('error', '請替每一節選擇同意或拒絕。');
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_approving_a_period_rejects_only_the_overlapping_period_of_other_requests(): void
    {
        // A：第 4 天第 5、6 節（09:00–10:00、10:10–11:00）；B：第 4 天第 5 節。B 先被同意。
        $a = $this->request([[4, '09:00', '10:00'], [4, '10:10', '11:00']], ['class_name' => 'A 班']);
        $b = $this->request([[4, '09:00', '10:00']], ['class_name' => 'B 班']);
        $c = $this->request([[4, '13:00', '14:00']], ['class_name' => 'C 班']);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        // A 沒有選整筆：同意 B 只會拒絕 A 重疊的那一節，所以不另外提醒。
        $this->actingAs($manager)->get(route('reservations.show', $b))
            ->assertDontSee('會整筆自動拒絕')->assertDontSee('A 班')->assertDontSee('C 班');

        $this->post(route('reservations.approve', $b))
            ->assertSessionHas('success', '預約已核准。其他申請裡重疊的 1 節已自動拒絕。');

        // A 的第 5 節自動拒絕，第 6 節仍待審核、可以同意。
        $a->refresh();
        $this->assertSame('pending', $a->status);
        $this->assertSame(['rejected', null], $a->slots->pluck('status')->all());
        $this->assertStringContainsString('已核准給B 班，這一節自動拒絕。', $a->slots[0]->rejection_reason);

        $this->get(route('reservations.show', $a))->assertSee('待審核')->assertSee('已核准給B 班，這一節自動拒絕。');
        $this->post(route('reservations.review', $a), ['decisions' => [$a->slots[1]->id => 'approved']])
            ->assertSessionHas('success', '預約已部分核准。');
        $this->assertSame('partially_approved', $a->fresh()->status);

        // 不重疊的 C 不受影響。
        $this->assertSame('pending', $c->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['description' => '系統自動拒絕重疊時段：A 班']);
    }

    public function test_an_all_or_nothing_request_is_rejected_as_a_whole_when_one_period_is_taken(): void
    {
        $a = $this->request([[4, '09:00', '10:00'], [4, '10:10', '11:00']], ['class_name' => 'A 班', 'is_all_or_nothing' => true]);
        $b = $this->request([[4, '09:00', '10:00']], ['class_name' => 'B 班']);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        // 整筆制只能全部同意或全部拒絕。
        $this->actingAs($manager)->get(route('reservations.show', $a))
            ->assertSee('申請人選了整筆：只能全部同意或全部拒絕')
            ->assertSee('<dd class="rd-wrap rd-full text-danger fw-bold">其中一節無法借用就整筆不要</dd>', false)
            ->assertDontSee('借用方式');
        // 審核 B 時先提醒：A 選了整筆，同意 B 會讓 A 整筆自動拒絕。
        $this->get(route('reservations.show', $b))->assertSee('會整筆自動拒絕')->assertSee('A 班（重疊');
        $this->post(route('reservations.review', $a), ['decisions' => [$a->slots[0]->id => 'approved', $a->slots[1]->id => 'rejected']])
            ->assertSessionHas('error', '申請人選了「其中一節無法借用就整筆不要」，只能全部同意或全部拒絕。');

        $this->post(route('reservations.approve', $b))->assertSessionHas('success');

        $a->refresh();
        $this->assertSame('rejected', $a->status);
        $this->assertNull($a->reviewed_by);
        $this->assertSame(['rejected', 'rejected'], $a->slots->pluck('status')->all());
        $this->assertStringContainsString('整筆申請自動拒絕', $a->rejection_reason);
    }

    public function test_a_pending_period_that_overlaps_an_approved_one_is_rejected_automatically(): void
    {
        // 別人已核准第 3 天 09:00–10:00（例如改版前就核准的資料）。
        $this->request([[3, '09:00', '10:00']], ['class_name' => '已核准班級', 'status' => 'approved']);
        $reservation = $this->request([[3, '09:30', '10:30'], [4, '09:00', '10:00']]);

        // 打開空間申請列表時就自動拒絕重疊的那一節，不用再操作；另一節仍待審核。
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.index'))->assertOk();

        $reservation->refresh();
        $this->assertSame('pending', $reservation->status);
        $this->assertSame(['rejected', null], $reservation->slots->pluck('status')->all());
        $this->assertStringContainsString('已核准給已核准班級，這一節自動拒絕。', $reservation->slots[0]->rejection_reason);
    }

    public function test_an_overdue_request_stays_pending_and_can_still_be_approved(): void
    {
        $reservation = $this->request([[1, '09:00', '10:00']], ['class_name' => '補審班']);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        // 時間過了還沒審核：不會自動拒絕，仍是待審核。
        Carbon::setTestNow(now()->addDays(1)->setTime(10, 30));
        $this->actingAs($manager)->get(route('reservations.index'))->assertOk();
        $this->assertSame('pending', $reservation->fresh()->status);

        // 之後補同意：課表上那一節變成「已借用」，審核的人看得到班級。
        $this->post(route('reservations.approve', $reservation))->assertSessionHas('success', '預約已核准。');
        $this->assertSame('approved', $reservation->fresh()->status);

        $week = $this->getJson(route('reservations.timetable.week', ['classroom_id' => $this->classroom->id, 'date' => now()->toDateString()]))->assertOk();
        $approved = collect($week->json('days'))->pluck('periods')->flatten(1)->pluck('reservations')->flatten(1)->where('status', 'approved');
        $this->assertSame('補審班', $approved->first()['class_name'] ?? null);

        // 沒有審核權限的人只看到「已借用」，不知道是哪一班。
        $this->actingAs($this->applicant);
        $week = $this->getJson(route('reservations.timetable.week', ['classroom_id' => $this->classroom->id, 'date' => now()->toDateString()]))->assertOk();
        $this->assertStringNotContainsString(trim(json_encode('補審班'), '"'), $week->getContent());
        Carbon::setTestNow();
    }

    public function test_cleanup_removes_rejected_and_overdue_pending_requests_only(): void
    {
        $rejected = $this->request([[3, '09:00', '10:00']], ['status' => 'rejected', 'reviewed_at' => now()]);
        $overdue = $this->request([[1, '09:00', '10:00']]);
        $upcoming = $this->request([[5, '09:00', '10:00']]);
        $approved = $this->request([[1, '13:00', '14:00']], ['status' => 'approved', 'reviewed_at' => now()]);
        $partial = $this->request([[1, '15:00', '16:00']], ['status' => 'partially_approved', 'reviewed_at' => now()]);

        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('reservations:cleanup')->expectsOutput('已清除 2 筆空間申請。')->assertSuccessful();

        $this->assertNull(ClassroomReservation::find($rejected->id));
        $this->assertNull(ClassroomReservation::find($overdue->id));
        $this->assertDatabaseMissing('classroom_reservation_slots', ['classroom_reservation_id' => $overdue->id]);
        $this->assertNotNull(ClassroomReservation::find($upcoming->id));
        $this->assertNotNull(ClassroomReservation::find($approved->id));
        $this->assertNotNull(ClassroomReservation::find($partial->id));
        Carbon::setTestNow();
    }

    public function test_cleanup_is_scheduled_on_the_monday_after_the_second_and_fourth_sunday(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'reservations:cleanup'));
        $this->assertNotNull($event);
        $this->assertSame('0 0 * * 1', $event->expression);

        // 2026-11：第二個週日 11/8 → 11/9（一）執行；第四個週日 11/22 → 11/23 執行；11/2、11/16、11/30 不執行。
        foreach (['2026-11-09' => true, '2026-11-23' => true, '2026-11-02' => false, '2026-11-16' => false, '2026-11-30' => false] as $monday => $runs) {
            Carbon::setTestNow($monday . ' 00:00:00');
            $this->assertSame($runs, $event->filtersPass($this->app), $monday);
        }
        Carbon::setTestNow();
    }

    public function test_old_partially_approved_requests_still_show_each_period_result(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00'], [3, '13:00', '14:00']], ['status' => 'partially_approved', 'reviewed_at' => now()]);
        $reservation->slots[0]->update(['status' => 'approved']);
        $reservation->slots[1]->update(['status' => 'rejected', 'rejection_reason' => '下午有考試']);

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.show', $reservation))
            ->assertSee('部分核准')->assertSee('下午有考試')->assertDontSee('拒絕原因（選填）');
    }

    public function test_requester_cannot_review_their_own_request(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->applicant)->post(route('reservations.approve', $reservation))->assertForbidden();
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_request_list_can_be_searched_and_shows_chinese_pagination(): void
    {
        $other = $this->makeUserWithRole('teacher', '教師', ['name' => '王小明']);
        $this->request([[3, '09:00', '10:00']], ['class_name' => '資五孝']);
        $this->request([[5, '09:00', '10:00']], ['class_name' => '一年甲班', 'requester_id' => $other->id, 'status' => 'approved']);
        $date = now()->addDays(5)->format('Y-m-d');

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->get(route('reservations.index'))
            ->assertOk()
            ->assertSee('tb-table-wrap', false)
            ->assertSee('顯示第 1 到 2 筆，共 2 筆');

        // 一個輸入框：申請班級、申請人、申請狀態、申請時段（日期或節次）、送出時間、教室都找得到。
        $find = fn (string $keyword) => $this->get(route('reservations.index', ['keyword' => $keyword]));
        $find('資五')->assertSee('資五孝')->assertDontSee('一年甲班');
        $find('小明')->assertSee('一年甲班')->assertDontSee('資五孝');
        $find('已核准')->assertSee('一年甲班')->assertDontSee('資五孝');
        $find('待審核')->assertSee('資五孝')->assertDontSee('一年甲班');
        $find($date)->assertSee('一年甲班')->assertDontSee('資五孝');
        $find(str_replace('-', '/', $date))->assertSee('一年甲班')->assertDontSee('資五孝');
        $find(now()->format('Y-m-d'))->assertSee('資五孝')->assertSee('一年甲班');   // 送出時間
        $find('A101')->assertSee('資五孝')->assertSee('一年甲班');                    // 教室代碼
        $find('不存在')->assertSee('找不到符合搜尋條件的申請');

        $period = $this->request([[6, '13:10', '14:00']], ['class_name' => '節次班']);
        $period->slots[0]->update(['period_number' => 6]);
        $find('第 6 節')->assertSee('節次班')->assertDontSee('資五孝');

        // 統計卡數字跟著搜尋條件，切換統計卡時保留搜尋條件。
        $this->get(route('reservations.index', ['keyword' => '資五']))
            ->assertSee('href="' . e(route('reservations.index', ['keyword' => '資五', 'status' => 'pending'])) . '"', false);

        // 超過 15 筆時有分頁，摘要文字也是中文。
        foreach (range(1, 15) as $i) {
            $this->request([[3, '09:00', '10:00']], ['class_name' => '加班' . $i]);
        }
        $this->get(route('reservations.index'))->assertSee('顯示第')->assertSee('筆，共')->assertDontSee('Showing')->assertDontSee('results');
    }
}
