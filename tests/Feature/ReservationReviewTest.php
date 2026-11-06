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
 * 空間預約：列表只顯示送出時間／申請人／申請班級／已審核或未審核；詳細頁只能整筆核准或整筆拒絕。
 * 核准一筆後，時段重疊的其他待審核申請整筆自動拒絕；最早的時段已經開始仍未審核的申請也會自動拒絕。
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
            ->assertSee('href="' . route('reservations.show', $pending) . '" class="tb-list-card" data-modal-frame data-modal-title="預約詳細"', false)
            ->assertDontSee('審核時間');
    }

    public function test_detail_page_is_visible_to_the_requester_and_managers_only(): void
    {
        $reservation = $this->request([[3, '09:00', '10:00']]);

        $this->actingAs($this->applicant)->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('班級活動')->assertDontSee('拒絕原因（選填）');

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('reservations.show', $reservation))->assertForbidden();

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('拒絕原因（選填）')->assertSee('>核准</button>', false)->assertSee('>拒絕</button>', false);
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
            ->assertSeeInOrder(['申請狀態', '送出時間', '教室', '申請班級', '申請人', '申請時段', '用途', '拒絕原因（選填）'])
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

    public function test_the_per_period_review_route_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('reservations.review'));
    }

    public function test_approving_one_request_rejects_every_overlapping_pending_request_as_a_whole(): void
    {
        // A：第 3 天、第 4 天的 09:00–10:00；B：第 4 天 09:30–10:20（和 A 的第 4 天重疊）；C：第 4 天 13:00（不重疊）。
        $a = $this->request([[3, '09:00', '10:00'], [4, '09:00', '10:00']], ['class_name' => 'A 班']);
        $b = $this->request([[4, '09:30', '10:20']], ['class_name' => 'B 班']);
        $c = $this->request([[4, '13:00', '14:00']], ['class_name' => 'C 班']);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        // 審核 B 時先提醒會自動拒絕 A。
        $this->actingAs($manager)->get(route('reservations.show', $b))
            ->assertSee('核准後，下列時段重疊的待審核申請會整筆自動拒絕')->assertSee('A 班')->assertDontSee('C 班');

        $this->post(route('reservations.approve', $b))
            ->assertSessionHas('success', '預約已核准。時段重疊的 1 筆待審核申請已自動拒絕。');

        // A 整筆拒絕：連沒重疊的第 3 天也一起拒絕；審核人是系統。
        $a->refresh();
        $this->assertSame('rejected', $a->status);
        $this->assertNull($a->reviewed_by);
        $this->assertNotNull($a->reviewed_at);
        $this->assertSame(['rejected', 'rejected'], $a->slots->pluck('status')->all());
        $this->assertSame('因為' . now()->addDays(4)->format('Y-m-d') . ' 09:30–10:20 已核准給B 班，整筆申請自動拒絕。', $a->rejection_reason);

        $this->get(route('reservations.show', $a))->assertSee('系統自動')->assertSee('已核准給B 班，整筆申請自動拒絕。')->assertDontSee('拒絕原因（選填）');

        // 不重疊的 C 不受影響。
        $this->assertSame('pending', $c->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['description' => '系統自動拒絕空間預約：A 班']);
    }

    public function test_a_pending_request_that_overlaps_an_approved_one_is_rejected_automatically(): void
    {
        // 別人已核准第 3 天 09:00–10:00（例如改版前就核准的資料）。
        $this->request([[3, '09:00', '10:00']], ['class_name' => '已核准班級', 'status' => 'approved']);
        $reservation = $this->request([[3, '09:30', '10:30'], [4, '09:00', '10:00']]);

        // 打開空間申請列表時就自動整筆拒絕，不用再操作。
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.index'))->assertOk();

        $reservation->refresh();
        $this->assertSame('rejected', $reservation->status);
        $this->assertNull($reservation->reviewed_by);
        $this->assertSame(['rejected', 'rejected'], $reservation->slots->pluck('status')->all());
        $this->assertStringStartsWith('因為', $reservation->rejection_reason);
        $this->assertStringContainsString('已核准給已核准班級，整筆申請自動拒絕。', $reservation->rejection_reason);

        $this->get(route('reservations.show', $reservation))->assertSee('系統自動')->assertDontSee('拒絕原因（選填）');
    }

    public function test_pending_request_is_rejected_automatically_once_its_first_period_has_started(): void
    {
        Carbon::setTestNow(now()->startOfDay()->setTime(8, 30));

        // 第 1 節今天 08:10 已開始、另一節明天：整筆自動拒絕。還沒開始的申請不受影響。
        $started = $this->request([[0, '08:10', '09:00'], [1, '08:10', '09:00']], ['class_name' => '過期班']);
        $later = $this->request([[0, '09:10', '10:00']], ['class_name' => '晚點班']);

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.index'))->assertOk();

        $started->refresh();
        $this->assertSame('rejected', $started->status);
        $this->assertNull($started->reviewed_by);
        $this->assertSame(['rejected', 'rejected'], $started->slots->pluck('status')->all());
        $this->assertStringContainsString('已經開始仍未審核，系統自動拒絕', $started->rejection_reason);
        $this->assertSame('pending', $later->fresh()->status);

        // 排程指令也會做同一件事。
        Carbon::setTestNow(now()->setTime(9, 15));
        $this->artisan('reservations:expire-pending')->assertSuccessful();
        $this->assertSame('rejected', $later->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_an_expired_request_cannot_be_approved(): void
    {
        $reservation = $this->request([[1, '09:00', '10:00']]);
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');

        // 管理者打開詳細頁之後，時間過了才按核准。
        Carbon::setTestNow(now()->addDays(1)->setTime(9, 5));
        $this->actingAs($manager)->post(route('reservations.approve', $reservation))
            ->assertSessionHas('error', '這筆申請的時段已經開始，系統已自動拒絕，無法再核准。');
        $this->assertSame('rejected', $reservation->fresh()->status);
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
            ->assertSee('tb-list tb-list-grouped', false)
            ->assertSee('顯示第 1 到 2 筆，共 2 筆');

        $this->get(route('reservations.index', ['class_name' => '資五']))->assertSee('資五孝')->assertDontSee('一年甲班');
        $this->get(route('reservations.index', ['requester' => '小明']))->assertSee('一年甲班')->assertDontSee('資五孝');
        $this->get(route('reservations.index', ['result' => 'approved']))->assertSee('一年甲班')->assertDontSee('資五孝');
        $this->get(route('reservations.index', ['slot_date' => $date]))->assertSee('一年甲班')->assertDontSee('資五孝');
        $this->get(route('reservations.index', ['classroom_id' => $this->classroom->id, 'submitted_on' => now()->format('Y-m-d')]))
            ->assertSee('資五孝')->assertSee('一年甲班');
        $this->get(route('reservations.index', ['class_name' => '不存在']))->assertSee('找不到符合搜尋條件的申請');

        // 統計卡數字跟著搜尋條件，切換統計卡時保留搜尋條件。
        $this->get(route('reservations.index', ['class_name' => '資五']))
            ->assertSee('href="' . e(route('reservations.index', ['class_name' => '資五', 'status' => 'pending'])) . '"', false);

        // 超過 15 筆時有分頁，摘要文字也是中文。
        foreach (range(1, 15) as $i) {
            $this->request([[3, '09:00', '10:00']], ['class_name' => '加班' . $i]);
        }
        $this->get(route('reservations.index'))->assertSee('顯示第')->assertSee('筆，共')->assertDontSee('Showing')->assertDontSee('results');
    }
}
