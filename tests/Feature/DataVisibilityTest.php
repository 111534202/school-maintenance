<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 資料可見範圍：沒有權限看的資料，列表、詳細頁、匯出、選單都不能出現。
 * 另外檢查「選單一致」：選單上顯示的每一個連結，點進去都不能是 403（看得到卻進不去）。
 */
class DataVisibilityTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function repairFor(User $requester, string $title, ?User $assignee = null): RepairRequest
    {
        return RepairRequest::factory()->create(['title' => $title, 'requester_id' => $requester->id, 'assigned_to' => $assignee?->id]);
    }

    public function test_repair_board_shows_each_person_only_the_cases_they_may_see(): void
    {
        $teacherA = $this->makeUserWithRole('teacher', '教師', ['name' => '教師甲']);
        $teacherB = User::factory()->create(['role_id' => $teacherA->role_id, 'name' => '教師乙']);
        $technician = $this->makeTechnician();
        $this->repairFor($teacherA, '甲的投影機壞了');
        $this->repairFor($teacherB, '乙的電腦壞了');
        $this->repairFor($teacherB, '指派給維修人員的冷氣', $technician);

        // 一般教師：只看到自己報修的，看不到別人的（連標題都不能出現在頁面上）。
        $this->actingAs($teacherA)->get(route('repairs.index'))
            ->assertOk()->assertSee('甲的投影機壞了')->assertDontSee('乙的電腦壞了')->assertDontSee('指派給維修人員的冷氣');

        // 維修人員：只看到指派給自己的。
        $this->actingAs($technician)->get(route('repairs.index'))
            ->assertOk()->assertSee('指派給維修人員的冷氣')->assertDontSee('甲的投影機壞了')->assertDontSee('乙的電腦壞了');

        // 什麼權限都沒有、也沒有任何案件的人：一筆都看不到。
        $nobody = User::factory()->create(['role_id' => Role::create(['name' => '無權限', 'role_code' => 'nobody', 'is_system' => false, 'permissions' => []])->id]);
        $this->actingAs($nobody)->get(route('repairs.index'))
            ->assertOk()->assertDontSee('甲的投影機壞了')->assertDontSee('乙的電腦壞了');

        // 有派工權限的主管與管理員：全部都看得到。
        foreach (['executive' => '主管', 'it_manager' => '設備管理員', 'admin' => '系統管理員'] as $slug => $label) {
            $this->actingAs($this->makeUserWithRole($slug, $label))->get(route('repairs.index'))
                ->assertOk()->assertSee('甲的投影機壞了')->assertSee('乙的電腦壞了');
        }
    }

    public function test_other_peoples_repair_detail_and_export_are_not_reachable(): void
    {
        $teacherA = $this->makeUserWithRole('teacher', '教師');
        $teacherB = User::factory()->create(['role_id' => $teacherA->role_id]);
        $others = $this->repairFor($teacherB, '乙的電腦壞了');

        $this->actingAs($teacherA)->get(route('repairs.show', $others))->assertForbidden();
        $this->actingAs($teacherA)->get(route('repairs.edit', $others))->assertForbidden();
    }

    public function test_reservation_list_shows_non_managers_only_their_own_requests(): void
    {
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $teacherA = $this->makeUserWithRole('teacher', '教師');
        $teacherB = User::factory()->create(['role_id' => $teacherA->role_id]);
        foreach ([[$teacherA, '甲班專用活動'], [$teacherB, '乙班專用活動']] as [$user, $class]) {
            ClassroomReservation::create(['classroom_id' => $classroom->id, 'requester_id' => $user->id, 'class_name' => $class, 'reservation_date' => now()->addDay()->toDateString(),
                'start_time' => '08:10', 'end_time' => '09:00', 'purpose' => '班級活動', 'status' => 'pending']);
        }

        $this->actingAs($teacherA)->get(route('reservations.index'))->assertOk()->assertSee('甲班專用活動')->assertDontSee('乙班專用活動');
        $this->actingAs($teacherA)->get(route('reservations.mine'))->assertOk()->assertSee('甲班專用活動')->assertDontSee('乙班專用活動');

        // 有審核權限的人看得到全部申請。
        $this->actingAs($this->makeItManager())->get(route('reservations.index'))->assertOk()->assertSee('甲班專用活動')->assertSee('乙班專用活動');
    }

    public function test_unpublished_knowledge_base_articles_are_hidden_from_people_who_cannot_manage_them(): void
    {
        KnowledgeBase::factory()->create(['title' => '已上架的文章', 'is_published' => true]);
        $draft = KnowledgeBase::factory()->create(['title' => '還沒公開的草稿', 'is_published' => false]);

        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher)->get(route('knowledge-base.index'))->assertOk()->assertSee('已上架的文章')->assertDontSee('還沒公開的草稿');
        $this->actingAs($teacher)->get(route('knowledge-base.show', $draft))->assertNotFound();

        $manager = $this->makeItManager();
        $this->actingAs($manager)->get(route('knowledge-base.index'))->assertSee('還沒公開的草稿');
    }

    public function test_every_link_in_the_menu_leads_somewhere_the_person_is_allowed_to_open(): void
    {
        $nobody = User::factory()->create(['role_id' => Role::create(['name' => '無權限', 'role_code' => 'nobody', 'is_system' => false, 'permissions' => []])->id]);
        $users = [
            'admin' => $this->makeUserWithRole('admin', '系統管理員'),
            'it_manager' => $this->makeItManager(),
            'technician' => $this->makeTechnician(),
            'teacher' => $this->makeUserWithRole('teacher', '教師'),
            'executive' => $this->makeUserWithRole('executive', '主管'),
            'nobody' => $nobody,
        ];

        foreach ($users as $roleName => $user) {
            $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
            preg_match_all('/href="(https?:\/\/[^"]*|\/[^"#]*)"/', $html, $matches);
            $paths = collect($matches[1])
                ->map(fn ($href) => parse_url($href, PHP_URL_PATH) ?: '/')
                ->filter(fn ($path) => ! preg_match('#^/(logout|locale|storage|vendor|css|js|build|favicon|robots)#', $path))
                ->unique()->values();

            $this->assertGreaterThan(0, $paths->count(), "{$roleName}：頁面上找不到任何連結，檢查沒有意義。");
            foreach ($paths as $path) {
                $status = $this->actingAs($user)->get($path)->getStatusCode();
                $this->assertNotSame(403, $status, "{$roleName}：頁面上有連結 {$path}，點進去卻是 403（看得到卻進不去）。");
                $this->assertLessThan(500, $status, "{$roleName}：連結 {$path} 出現伺服器錯誤 {$status}。");
            }
        }
    }
}
