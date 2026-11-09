<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 免登入查詢課表（/schedule）：沿用空間預約頁面；不顯示「我的申請」、不能送出；
 * 課表資料不透露是哪一班預約的。
 */
class GuestScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(string $name = 'A101 教室'): Classroom
    {
        return Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => $name,
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    public function test_login_page_links_to_the_guest_schedule(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('查詢課表（免登入）')
            ->assertSee(route('schedule'), false);
    }

    public function test_guest_can_open_the_schedule_without_my_requests(): void
    {
        $this->classroom('電腦教室一');

        $this->get(route('schedule'))
            ->assertOk()
            ->assertSee('查詢課表')
            ->assertSee('電腦教室一')
            ->assertSee('若需預約請先登入')
            ->assertSee(json_encode(route('schedule.timetable.week')), false)   // 課表改查免登入的網址
            ->assertSee(route('schedule.login'), false)
            ->assertDontSee('我的申請')
            ->assertDontSee('id="my-requests"', false);
    }

    public function test_signed_in_users_are_sent_to_the_reservation_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('schedule'))->assertRedirect(route('reservations.create'));
    }

    public function test_guest_timetable_hides_which_class_reserved(): void
    {
        $classroom = $this->classroom();
        $date = now()->addDays(7)->startOfWeek()->addDay();
        $reservation = ClassroomReservation::create([
            'classroom_id' => $classroom->id,
            'requester_id' => User::factory()->create()->id,
            'class_name' => '資五孝',
            'reservation_date' => $date->toDateString(),
            'start_time' => '08:10',
            'end_time' => '09:00',
            'purpose' => '班級活動',
            'status' => 'approved',
        ]);
        $reservation->slots()->create(['reservation_date' => $date->toDateString(), 'start_time' => '08:10', 'end_time' => '09:00']);

        $response = $this->getJson(route('schedule.timetable.week', ['classroom_id' => $classroom->id, 'date' => $date->toDateString()]))
            ->assertOk();

        $this->assertStringNotContainsString('資五孝', $response->getContent());
        // 確認這筆預約真的有出現在課表裡（只是沒有班級名稱）。
        $reservations = collect($response->json('days'))->pluck('periods')->flatten(1)->pluck('reservations')->flatten(1);
        $this->assertNotEmpty($reservations);
        $this->assertSame('approved', $reservations->first()['status']);
        $this->assertArrayNotHasKey('class_name', $reservations->first());
    }

    public function test_guest_cannot_submit_a_reservation(): void
    {
        $classroom = $this->classroom();

        $this->post(route('reservations.store'), ['classroom_id' => $classroom->id, 'class_name' => '資五孝'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, ClassroomReservation::count());
    }

    public function test_login_button_returns_to_the_reservation_page_after_signing_in(): void
    {
        $this->get(route('schedule.login'))->assertRedirect(route('login'));
        $this->assertSame(route('reservations.create'), session('url.intended'));
    }
}
