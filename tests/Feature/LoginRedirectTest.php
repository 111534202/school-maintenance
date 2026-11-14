<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 登入後一律回主控台：登出後再登入、被登入頁擋下後再登入，都不回到上次最後停留的畫面。
 * 例外：公開「查詢課表」頁按「登入」是要去申請空間預約，這個要保留。
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function userWithPassword(): User
    {
        $user = $this->makeUserWithRole('teacher', '教師');
        $user->forceFill(['password' => 'Passw0rd!x'])->save();

        return $user;
    }

    public function test_logging_in_again_after_logout_goes_to_the_dashboard(): void
    {
        $user = $this->userWithPassword();

        $this->actingAs($user)->get(route('repairs.index'))->assertOk();   // 最後停留在報修頁
        $this->post(route('logout'))->assertRedirect('/login');

        $this->post(route('login'), ['login' => $user->email, 'password' => 'Passw0rd!x'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_page_that_sent_the_visitor_to_login_is_not_returned_to(): void
    {
        $user = $this->userWithPassword();

        $this->get(route('repairs.index'))->assertRedirect(route('login'));   // 沒登入被擋下，Laravel 會記住想去的頁面

        $this->post(route('login'), ['login' => $user->email, 'password' => 'Passw0rd!x'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_from_the_public_schedule_page_still_goes_to_the_reservation_form(): void
    {
        $user = $this->userWithPassword();

        $this->get(route('schedule.login'))->assertRedirect(route('login'));

        $this->post(route('login'), ['login' => $user->email, 'password' => 'Passw0rd!x'])
            ->assertRedirect(route('reservations.create'));
    }
}
