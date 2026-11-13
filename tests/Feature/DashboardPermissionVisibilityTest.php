<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 主控台依權限整張卡片隱藏：沒有對應權限的人，看不到那個項目（而不是看到「0」）。
 * 項目與需要的權限見 resources/views/dashboard.blade.php 的 $cards（每張卡片的 'can'）。
 */
class DashboardPermissionVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function dashboardAs(string $role, string $name)
    {
        return $this->actingAs($this->makeUserWithRole($role, $name))->get(route('dashboard'))->assertOk();
    }

    // 教師：能驗收（repairs.accept）、不能派工、不能審核空間預約、不能管主檔。
    public function test_teacher_sees_review_but_not_dispatch_reservation_or_master_cards(): void
    {
        $this->dashboardAs('teacher', '教師')
            ->assertSee('待驗收')
            ->assertDontSee('待派工')
            ->assertDontSee('空間預約待審核')
            ->assertDontSee('設備總數')
            ->assertDontSee('用戶總數')
            ->assertDontSee('教室數量');
    }

    // 維修人員：能維修（repairs.process），沒有派工、驗收、審核預約的權限。
    public function test_technician_does_not_see_dispatch_review_or_reservation_cards(): void
    {
        $this->dashboardAs('technician', '維修人員')
            ->assertSee('維修中')
            ->assertSee('逾時工單')
            ->assertDontSee('待派工')
            ->assertDontSee('待驗收')
            ->assertDontSee('空間預約待審核');
    }

    // 資訊組主管：能派工、能審核空間預約，但沒有驗收權限。
    public function test_it_manager_sees_dispatch_and_reservation_cards_but_not_review(): void
    {
        $this->dashboardAs('it_manager', '資訊組主管')
            ->assertSee('待派工')
            ->assertSee('空間預約待審核')
            ->assertDontSee('待驗收');
    }

    // 只有「提出報修」權限的人：沒有維修、派工、驗收的權限，就看不到工時統計與逾時工單，只留自己的報修進度。
    public function test_a_requester_only_role_sees_only_own_repair_progress_cards(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['repairs.create']))->get(route('dashboard'))->assertOk()
            ->assertSee('進行中工單')
            ->assertSee('本月新增報修')
            ->assertDontSee('本月平均累計維修工時')
            ->assertDontSee('逾時工單')
            ->assertDontSee('待派工')
            ->assertDontSee('待驗收')
            ->assertDontSee('空間預約待審核');
    }
}
