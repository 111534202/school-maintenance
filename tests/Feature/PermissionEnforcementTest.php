<?php

namespace Tests\Feature;

use App\Mail\RepairDispatchedMail;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 身分主檔勾選的權限，要真的控制每個頁面與動作（路由、按鈕、選單、下拉選單、通知信）。 */
class PermissionEnforcementTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    // 輔助方法：建立一張指定狀態的報修單。
    private function repair(string $status): RepairRequest
    {
        return RepairRequest::factory()->create(['status' => $status]);
    }

    // 沒登入的人開任何頁面，一律被導到登入頁。
    public function test_guests_are_sent_to_login_for_everything(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('repairs.index'))->assertRedirect(route('login'));
    }

    // 系統管理員不必勾選任何權限，就能開啟所有頁面。
    public function test_admin_has_every_permission_without_ticking_anything(): void
    {
        $this->loginAsAnyUser();

        foreach (['users.index', 'roles.index', 'departments.index', 'classrooms.index', 'device-categories.index', 'devices.index', 'audit-logs.index', 'repairs.index', 'knowledge-base.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    // 資訊組主管能進設備、教室、設備類別、操作紀錄；但進不了用戶、身分、部門主檔（403）。
    public function test_it_manager_gets_device_masters_but_not_user_or_role_management(): void
    {
        $this->actingAs($this->makeItManager());

        foreach (['classrooms.index', 'device-categories.index', 'devices.index', 'audit-logs.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['users.index', 'roles.index', 'departments.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    // 左邊選單只顯示這個人有權限的連結。
    public function test_sidebar_only_shows_links_the_user_has_permission_for(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('dashboard'))
            ->assertSee('教室主檔')->assertSee('設備主檔')
            ->assertDontSee('用戶主檔')->assertDontSee('身分主檔')->assertDontSee('部門主檔');

        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $this->get(route('dashboard'))
            ->assertSee('用戶主檔')->assertSee('身分主檔')->assertSee('部門主檔')->assertSee('教室主檔')->assertSee('設備主檔');

        $this->actingAs($this->makeTechnician());
        $this->get(route('dashboard'))->assertDontSee('主檔管理')->assertDontSee('教室主檔');
    }

    // 維修人員可以提出報修與處理維修，但不能派工、不能驗收。
    public function test_technician_can_submit_and_process_but_not_dispatch_or_accept(): void
    {
        $technician = $this->makeTechnician();
        $this->actingAs($technician);

        $this->get(route('repairs.create'))->assertOk();
        $this->post(route('repairs.assign', $this->repair('pending')), ['assigned_to' => $technician->id])->assertForbidden();
        $this->post(route('repairs.complete', $this->repair('pending_review')))->assertForbidden();
        $this->post(route('repairs.reject', $this->repair('pending_review')), ['rejection_reason' => 'x'])->assertForbidden();

        // 處理維修只限「指派給自己」的案件（歸屬規則見 SecurityHardeningTest）。
        $assigned = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        $this->post(route('repairs.start', $assigned))->assertRedirect(route('repairs.show', $assigned));
        $this->assertSame('in_progress', $assigned->fresh()->status->value);
        $this->get(route('repair-logs.create', $assigned))->assertOk();
    }

    // 教師可以提出報修與驗收，但不能處理維修、不能派工。
    public function test_teacher_can_submit_and_accept_but_not_process_or_dispatch(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.create'))->assertOk();
        $review = $this->repair('pending_review');
        $this->post(route('repairs.complete', $review))->assertRedirect(route('repairs.show', $review));
        $this->assertSame('completed', $review->fresh()->status->value);

        $this->post(route('repairs.start', $this->repair('assigned')))->assertForbidden();
        $this->get(route('repair-logs.create', $this->repair('in_progress')))->assertForbidden();
        $this->post(route('repairs.reassign', $this->repair('assigned')), ['assigned_to' => 1])->assertForbidden();
    }

    // 有派工權限的主管，可以把案件派給「可被指派」的維修人員。
    public function test_dispatch_permission_lets_executive_dispatch_to_an_assignable_technician(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();
        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $case = $this->repair('pending');

        $this->post(route('repairs.assign', $case), ['assigned_to' => $technician->id])
            ->assertRedirect(route('repairs.show', $case));

        $this->assertSame('assigned', $case->fresh()->status->value);
    }

    // 報修單詳細頁上的操作按鈕，依檢視者的權限（與案件狀態）顯示。
    public function test_buttons_on_the_case_page_follow_the_viewers_permissions(): void
    {
        $case = $this->repair('pending');

        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $this->get(route('repairs.show', $case))->assertSee('確認派工');

        // 維修人員沒有派工權限；而且這張單跟他無關，連詳細頁都打不開（403）。
        $technician = $this->makeTechnician();
        $this->actingAs($technician);
        $this->get(route('repairs.show', $case))->assertForbidden();
        $mine = RepairRequest::factory()->create(['status' => 'pending', 'assigned_to' => $technician->id]);
        $this->get(route('repairs.show', $mine))->assertOk()->assertDontSee('確認派工');

        // 教師只看得到、也只能驗收「自己報修」的案件。
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher);
        $own = RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher->id]);
        $this->get(route('repairs.show', $own))->assertSee('驗收通過，結案');
    }

    // 知識庫所有登入者都能看；新增、編輯、刪除需要管理權限。
    public function test_knowledge_base_is_readable_by_all_but_editable_only_with_permission(): void
    {
        $entry = KnowledgeBase::factory()->create();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('knowledge-base.index'))->assertOk();
        $this->get(route('knowledge-base.show', $entry))->assertOk();
        $this->get(route('knowledge-base.create'))->assertForbidden();
        $this->delete(route('knowledge-base.destroy', $entry))->assertForbidden();
        $this->assertDatabaseHas('knowledge_base', ['id' => $entry->id]);

        $this->actingAs($this->makeItManager());
        $this->get(route('knowledge-base.create'))->assertOk();
    }

    // 派工下拉選單與後端驗證，都只接受「身分可被指派、且帳號啟用中」的人。
    public function test_only_assignable_active_users_are_listed_and_accepted_as_technicians(): void
    {
        $active = $this->makeTechnician();
        $inactive = $this->makeUserWithRole('technician', '維修人員', ['name' => '已停用的維修員', 'is_active' => false]);
        $notAssignable = $this->makeUserWithRole('teacher', '教師', ['name' => '不可被指派的老師']);
        $custom = $this->makeUserWithPermissions(['repairs.assignable'], ['name' => '自訂身分維修員']);

        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        $case = $this->repair('pending');

        $page = $this->get(route('repairs.show', $case));
        $page->assertSee($active->name)->assertSee('自訂身分維修員');
        $page->assertDontSee('已停用的維修員')->assertDontSee('不可被指派的老師');

        $this->post(route('repairs.assign', $case), ['assigned_to' => $inactive->id])->assertSessionHasErrors('assigned_to');
        $this->post(route('repairs.assign', $case), ['assigned_to' => $notAssignable->id])->assertSessionHasErrors('assigned_to');
        $this->assertSame('pending', $case->fresh()->status->value);

        Mail::fake();
        $this->post(route('repairs.assign', $case), ['assigned_to' => $custom->id])->assertSessionHasNoErrors();
        $this->assertSame($custom->id, $case->fresh()->assigned_to);
    }

    // 派工通知信的副本，只寄給「身分勾選了接收副本、且帳號啟用中」的人。
    public function test_dispatch_notice_copy_goes_only_to_active_users_whose_role_ticks_notice_cc(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();
        $ccActive = $this->makeUserWithRole('it_manager', '資訊組主管', ['email' => 'cc.active@school.test']);
        $this->makeUserWithRole('it_manager', '資訊組主管', ['email' => 'cc.inactive@school.test', 'is_active' => false]);
        $custom = $this->makeUserWithPermissions(['repairs.notice_cc'], ['email' => 'cc.custom@school.test']);
        $this->makeUserWithRole('teacher', '教師', ['email' => 'not.cc@school.test']);

        $this->actingAs($ccActive);
        $this->post(route('repairs.assign', $this->repair('pending')), ['assigned_to' => $technician->id]);

        Mail::assertSent(RepairDispatchedMail::class, function (RepairDispatchedMail $mail) use ($technician) {
            return $mail->hasTo($technician->email)
                && $mail->hasCc('cc.active@school.test')
                && $mail->hasCc('cc.custom@school.test')
                && ! $mail->hasCc('cc.inactive@school.test')
                && ! $mail->hasCc('not.cc@school.test');
        });
    }

    // 沒有任何身分的用戶，沒有任何權限（連頁面都進不去）。
    public function test_user_without_a_role_has_no_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => null]));

        $this->get(route('repairs.index'))->assertOk();       // 看板所有登入者都能看
        $this->get(route('repairs.create'))->assertForbidden(); // 但沒有身分就什麼都不能做
        $this->get(route('users.index'))->assertForbidden();
    }
}
