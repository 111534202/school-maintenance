<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 「我的報修」：所有身分的選單都看得到，點進去只列自己提出的報修單（管理員與主管也一樣）。
 */
class MyRepairsTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_every_role_sees_my_repairs_in_the_menu(): void
    {
        $nobody = User::factory()->create(['role_id' => Role::create(['name' => '無權限', 'role_code' => 'nobody', 'is_system' => false, 'permissions' => []])->id]);
        $users = [
            $this->makeUserWithRole('admin', '系統管理員'), $this->makeItManager(), $this->makeTechnician(),
            $this->makeUserWithRole('teacher', '教師'), $this->makeUserWithRole('executive', '主管'), $nobody,
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee('我的報修')
                ->assertSee(route('repairs.index', ['mine' => 1]), false);
        }
    }

    public function test_the_mine_filter_lists_only_my_own_requests_even_for_people_who_can_see_everything(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $teacher = $this->makeUserWithRole('teacher', '教師');
        RepairRequest::factory()->create(['title' => '管理員自己報的', 'requester_id' => $admin->id]);
        RepairRequest::factory()->create(['title' => '教師報的投影機', 'requester_id' => $teacher->id]);

        $this->actingAs($admin)->get(route('repairs.index'))->assertSee('管理員自己報的')->assertSee('教師報的投影機');
        $this->actingAs($admin)->get(route('repairs.index', ['mine' => 1]))->assertSee('管理員自己報的')->assertDontSee('教師報的投影機');
        $this->actingAs($teacher)->get(route('repairs.index', ['mine' => 1]))->assertSee('教師報的投影機')->assertDontSee('管理員自己報的');
    }
}
