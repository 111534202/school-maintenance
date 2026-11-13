<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesReservations;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 用戶主檔（帳號管理）：權限、列表篩選、新增／編輯驗證、防呆規則、啟用停用、
 * 密碼重設、刪除還原、永久刪除，以及停用／刪除後不能登入。
 */
class UserManagementTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫，測試之間不會互相影響。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;
    use CreatesReservations;

    private User $admin;

    // setUp：每個測試開始前都會先執行一次；這裡先建立並登入一位系統管理員，因為用戶主檔只有管理員進得去。
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin('admin_main');
        $this->actingAs($this->admin);
    }

    // 輔助方法：建立一位指定帳號的系統管理員。
    private function makeAdmin(string $username, array $attrs = []): User
    {
        $role = Role::firstOrCreate(['role_code' => 'admin'], ['name' => '系統管理員']);

        return User::factory()->create(['role_id' => $role->id, 'account' => $username] + $attrs);
    }

    // 輔助方法：建立一位一般身分的同仁（預設是維修人員）。
    private function makeStaff(string $username, string $roleSlug = 'technician', array $attrs = []): User
    {
        $role = Role::firstOrCreate(['role_code' => $roleSlug], ['name' => $roleSlug]);

        return User::factory()->create(['role_id' => $role->id, 'account' => $username] + $attrs);
    }

    // 輔助方法：回傳一份「完全合法」的新增用戶表單內容，各測試只改自己要測的欄位。
    private function validPayload(array $overrides = []): array
    {
        $role = Role::firstOrCreate(['role_code' => 'technician'], ['name' => '維修人員']);

        return array_merge([
            'account' => 'new.user',
            'name' => '新同仁',
            'email' => 'new.user@example.com',
            'phone' => '0912-345-678',
            'role_id' => $role->id,
            'department_id' => '',
            'is_active' => '1',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ], $overrides);
    }

    // ---------- 權限 ----------

    // 沒登入的人開用戶主檔，會被導到登入頁。
    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();

        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    // 只有有「用戶主檔」權限的人能進；其他身分得到 403（沒有權限）。
    public function test_only_admin_role_can_open_user_master(): void
    {
        foreach (['it_manager', 'technician', 'teacher', 'executive'] as $slug) {
            $this->actingAs($this->makeStaff('u_' . $slug, $slug));

            $this->get(route('users.index'))->assertForbidden();
            $this->post(route('users.store'), $this->validPayload())->assertForbidden();
        }
    }

    // ---------- 列表與篩選 ----------

    // 列表會顯示每位用戶的身分與部門。
    public function test_index_lists_users_with_role_and_department(): void
    {
        $department = Department::create(['name' => '資訊組']);
        $this->makeStaff('wang', 'technician', ['name' => '王小明', 'department_id' => $department->id]);

        $response = $this->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('王小明');
        $response->assertSee('資訊組');
    }

    // 關鍵字可以搜尋帳號、姓名、Email、電話。
    public function test_index_can_filter_by_keyword_in_username_name_email_and_phone(): void
    {
        $this->makeStaff('alice', 'technician', ['name' => '愛麗絲', 'email' => 'alice@corp.test', 'phone' => '0900111222']);
        $this->makeStaff('bob', 'technician', ['name' => '鮑伯', 'email' => 'bob@corp.test', 'phone' => '0900333444']);

        foreach (['alice', '愛麗', 'alice@corp', '0900111'] as $keyword) {
            $this->get(route('users.index', ['keyword' => $keyword]))
                ->assertSee('愛麗絲')
                ->assertDontSee('鮑伯');
        }
    }

    // 可以依身分、部門、狀態（啟用／停用／已刪除）篩選。
    public function test_index_can_filter_by_role_department_and_status(): void
    {
        $department = Department::create(['name' => '總務處']);
        $tech = $this->makeStaff('tech_a', 'technician', ['name' => '技師甲', 'department_id' => $department->id]);
        $this->makeStaff('teacher_a', 'teacher', ['name' => '老師乙']);
        $this->makeStaff('off_a', 'technician', ['name' => '停用丙', 'is_active' => false]);
        $gone = $this->makeStaff('gone_a', 'technician', ['name' => '已刪丁']);
        $gone->delete();

        $this->get(route('users.index', ['role_id' => $tech->role_id]))
            ->assertSee('技師甲')->assertDontSee('老師乙');

        $this->get(route('users.index', ['department_id' => $department->id]))
            ->assertSee('技師甲')->assertDontSee('老師乙');

        $this->get(route('users.index', ['status' => 'inactive']))
            ->assertSee('停用丙')->assertDontSee('技師甲');

        $this->get(route('users.index', ['status' => 'active']))
            ->assertSee('技師甲')->assertDontSee('停用丙')->assertDontSee('已刪丁');

        $this->get(route('users.index', ['status' => 'deleted']))
            ->assertSee('已刪丁')->assertDontSee('技師甲');

        // 預設列表不顯示已刪除的帳號。
        $this->get(route('users.index'))->assertDontSee('已刪丁');
    }

    // ---------- 新增 ----------

    // 管理員能新增用戶；密碼在資料庫裡是加密的，而且不會被寫進操作紀錄。
    public function test_admin_can_create_a_user_and_password_is_hashed_and_not_logged(): void
    {
        $response = $this->post(route('users.store'), $this->validPayload());

        $response->assertRedirect(route('users.index'));
        $user = User::firstWhere('account', 'new.user');
        $this->assertNotNull($user);
        $this->assertSame('新同仁', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));

        $log = AuditLog::where('action', 'created')->where('loggable_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('secret-pass-1', json_encode($log->changes));
        $this->assertArrayNotHasKey('password', $log->changes);
    }

    // 新增時的驗證：必填欄位、帳號與 Email 不可重複、帳號格式、密碼長度與確認。
    public function test_create_validates_required_unique_and_format_rules(): void
    {
        // 先建立一位帳號與 Email 都已被使用的同仁，下面用它來測試「不可重複」。
        $this->makeStaff('taken', 'technician', ['email' => 'taken@example.com']);

        // 帳號與別人重複 → 帳號欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['account' => 'taken']))
            ->assertSessionHasErrors('account');
        // Email 與別人重複 → Email 欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
        // 帳號含空白與特殊符號 → 格式不合。
        $this->post(route('users.store'), $this->validPayload(['account' => 'bad name!']))
            ->assertSessionHasErrors('account');
        // 帳號太短（少於 3 字）→ 帳號欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['account' => 'ab']))
            ->assertSessionHasErrors('account');
        // 密碼太短（少於 8 碼）→ 密碼欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        // 兩次輸入的密碼不一致 → 密碼欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['password_confirmation' => 'different-pass']))
            ->assertSessionHasErrors('password');
        // 身分編號不存在 → 身分欄位出錯。
        $this->post(route('users.store'), $this->validPayload(['role_id' => 99999]))
            ->assertSessionHasErrors('role_id');
        // 姓名空白、Email 格式錯誤 → 兩個欄位都出錯。
        $this->post(route('users.store'), $this->validPayload(['name' => '', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseMissing('users', ['account' => 'new.user']);
    }

    // ---------- 編輯 ----------

    // 能修改用戶；不改帳號與 Email 時，不會被誤判成「與自己重複」。
    public function test_admin_can_update_a_user_and_keep_own_unique_values(): void
    {
        $user = $this->makeStaff('editme', 'technician', ['name' => '舊名', 'email' => 'old@example.com']);
        $department = Department::create(['name' => '教務處']);

        $response = $this->put(route('users.update', $user), $this->validPayload([
            'account' => 'editme',          // 沒改帳號：不能跟自己衝突
            'email' => 'old@example.com',    // 沒改 Email：不能跟自己衝突
            'name' => '新名',
            'department_id' => $department->id,
            'password' => '', 'password_confirmation' => '',
        ]));

        $response->assertRedirect(route('users.index'));
        $user->refresh();
        $this->assertSame('新名', $user->name);
        $this->assertSame($department->id, $user->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'loggable_id' => $user->id]);
    }

    // 編輯用戶時不會動到密碼（改密碼要走獨立的重設密碼）。
    public function test_update_does_not_change_the_password(): void
    {
        $user = $this->makeStaff('keeppass', 'technician', ['password' => 'original-pass']);

        $this->put(route('users.update', $user), $this->validPayload([
            'account' => 'keeppass', 'email' => $user->email, 'password' => 'hacked-pass1', 'password_confirmation' => 'hacked-pass1',
        ]))->assertRedirect();

        $this->assertTrue(Hash::check('original-pass', $user->fresh()->password));
    }

    // ---------- 防呆規則 ----------

    // 管理員不能把自己降級或停用自己（避免把自己鎖在系統外）。
    public function test_admin_cannot_demote_or_deactivate_self(): void
    {
        $techRole = Role::firstOrCreate(['role_code' => 'technician'], ['name' => '維修人員']);

        $this->put(route('users.update', $this->admin), $this->validPayload([
            'account' => 'admin_main', 'email' => $this->admin->email, 'role_id' => $techRole->id,
        ]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->isAdmin());

        $this->put(route('users.update', $this->admin), $this->validPayload([
            'account' => 'admin_main', 'email' => $this->admin->email, 'role_id' => $this->admin->role_id, 'is_active' => '0',
        ]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    // 管理員不能用快速按鈕停用或刪除自己。
    public function test_admin_cannot_toggle_or_delete_self(): void
    {
        $this->patch(route('users.toggle', $this->admin))->assertSessionHas('error');
        $this->delete(route('users.destroy', $this->admin))->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertFalse($this->admin->fresh()->trashed());
    }

    // 系統裡最後一位啟用中的管理員，不能被刪除、停用或降級。
    public function test_last_active_admin_cannot_be_deleted_deactivated_or_demoted(): void
    {
        // 操作者是一位「啟用中、有用戶主檔權限、但不是管理員」的帳號（已停用的帳號現在連頁面都進不去，
        // 見 EnsureUserIsActive），所以被操作的 target 就是系統裡唯一一位啟用中的管理員。
        // setUp 建立的 admin_main 先停用，這樣 only_admin 才真的是唯一一位啟用中的管理員。
        $this->admin->update(['is_active' => false]);
        $actor = $this->makeUserWithPermissions(['users.manage']);
        $target = $this->makeAdmin('only_admin');
        $this->actingAs($actor);

        $this->delete(route('users.destroy', $target))->assertSessionHas('error');
        $this->assertFalse($target->fresh()->trashed());

        $this->patch(route('users.toggle', $target))->assertSessionHas('error');
        $this->assertTrue($target->fresh()->is_active);

        $techRole = Role::firstOrCreate(['role_code' => 'technician'], ['name' => '維修人員']);
        $this->put(route('users.update', $target), $this->validPayload([
            'account' => 'only_admin', 'email' => $target->email, 'role_id' => $techRole->id,
        ]))->assertSessionHas('error');
        $this->assertTrue($target->fresh()->isAdmin());
    }

    // 只要還有另一位啟用中的管理員，就可以刪除這一位。
    public function test_an_admin_can_be_removed_when_another_active_admin_exists(): void
    {
        $other = $this->makeAdmin('second_admin');

        $this->delete(route('users.destroy', $other))->assertSessionHas('success');

        $this->assertTrue($other->fresh()->trashed());
    }

    // ---------- 啟用／停用 ----------

    // 啟用／停用切換有效，並且寫進操作紀錄。
    public function test_toggle_deactivates_and_reactivates_and_logs_the_change(): void
    {
        $user = $this->makeStaff('toggle_me');

        $this->patch(route('users.toggle', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->is_active);

        $this->patch(route('users.toggle', $user))->assertSessionHas('success');
        $this->assertTrue($user->fresh()->is_active);

        $this->assertSame(2, AuditLog::where('action', 'status_changed')->where('loggable_id', $user->id)->count());
    }

    // 停用帳號後，他目前已登入的連線會被立刻清掉（強制登出）。
    public function test_deactivating_signs_the_user_out_of_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->makeStaff('kick_me');
        DB::table('sessions')->insert([
            'id' => 'sess-kick-me', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit', 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->patch(route('users.toggle', $user));

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-kick-me']);
    }

    // ---------- 重設密碼 ----------

    // 管理員能重設他人密碼；新密碼不會被寫進操作紀錄。
    public function test_admin_can_reset_a_password_and_it_is_not_logged(): void
    {
        $user = $this->makeStaff('forgetful', 'technician', ['password' => 'old-password1']);

        $this->post(route('users.reset-password', $user), [
            'password' => 'brand-new-pass1', 'password_confirmation' => 'brand-new-pass1',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-pass1', $user->fresh()->password));
        $log = AuditLog::where('action', 'password_reset')->where('loggable_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('brand-new-pass1', json_encode($log->toArray()));
    }

    // 重設密碼要輸入兩次一致，而且至少 8 碼。
    public function test_reset_password_requires_confirmation_and_minimum_length(): void
    {
        $user = $this->makeStaff('weakpass', 'technician', ['password' => 'old-password1']);

        $this->post(route('users.reset-password', $user), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post(route('users.reset-password', $user), ['password' => 'long-enough-1', 'password_confirmation' => 'mismatch-1'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password1', $user->fresh()->password));
    }

    // ---------- 刪除與還原 ----------

    // 刪除是軟刪除（資料還在），而且可以還原。
    public function test_delete_is_soft_and_can_be_restored(): void
    {
        $user = $this->makeStaff('soft_del');

        $this->delete(route('users.destroy', $user))->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->patch(route('users.restore', $user->id))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'restored', 'loggable_id' => $user->id]);
    }

    // 永久刪除要先軟刪除過；還是啟用中的帳號不能直接整個清掉。
    public function test_force_delete_requires_the_account_to_be_soft_deleted_first(): void
    {
        $user = $this->makeStaff('not_deleted_yet');

        $this->delete(route('users.force-destroy', $user))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // 永久刪除是真的從資料庫移除（不是標記 deleted_at），操作紀錄也會記下來。
    public function test_force_delete_actually_removes_the_row_from_the_database(): void
    {
        $user = $this->makeStaff('to_purge');
        $user->delete();

        $this->delete(route('users.force-destroy', $user))->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'force_deleted', 'loggable_id' => $user->id]);
    }

    // 帳號還留著資料庫刻意擋下刪除的歷史紀錄（例如空間預約申請，見 classroom_reservations.requester_id
    // 的 restrictOnDelete）時，永久刪除要被擋下、顯示清楚的錯誤訊息，資料不變，不能讓系統出現 500 錯誤。
    public function test_force_delete_is_blocked_when_the_account_has_protected_reservation_history(): void
    {
        $user = $this->makeStaff('has_reservation_history');
        $classroom = Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F',
            'classroom_code' => 'FD101', 'name' => 'FD101 教室', 'type' => '一般教室',
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
        $this->makeReservation([
            'classroom_id' => $classroom->id,
            'requester_id' => $user->id,
            'class_name' => '一年甲班',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => '班級活動',
            'status' => 'pending',
        ]);
        $user->delete();

        $this->delete(route('users.force-destroy', $user))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // 只能永久刪除「完全沒有任何關聯資料」的帳號：留有報修單時擋下，並在訊息裡說明是哪一類、幾筆。
    // 否則資料庫會把報修單的報修人欄位悄悄設成空白，報修紀錄就永遠查不到是誰報修的。
    public function test_force_delete_is_blocked_when_the_account_has_repair_requests_and_names_them(): void
    {
        $user = $this->makeStaff('has_repairs');
        RepairRequest::factory()->count(2)->create(['requester_id' => $user->id]);
        $user->delete();

        $response = $this->delete(route('users.force-destroy', $user));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('報修單（報修人） 2 筆', session('error'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(2, RepairRequest::where('requester_id', $user->id)->count());   // 報修人沒有被清成空白
    }

    // 操作紀錄的「操作人」也算關聯資料：帳號做過任何會留下紀錄的操作，就不能永久刪除。
    public function test_force_delete_is_blocked_when_the_account_appears_as_actor_in_the_audit_log(): void
    {
        $user = $this->makeStaff('has_logs');
        AuditLog::create(['user_id' => $user->id, 'action' => 'created', 'description' => '測試操作']);
        $user->delete();

        $this->delete(route('users.force-destroy', $user))->assertSessionHas('error');

        $this->assertStringContainsString('操作紀錄（操作人） 1 筆', session('error'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(1, AuditLog::where('user_id', $user->id)->count());
    }

    // 完全沒有關聯資料的帳號（建立後從沒用過）可以永久刪除，連沒有外鍵的登入連線紀錄也一起清掉。
    public function test_force_delete_works_for_an_unused_account_and_clears_its_sessions(): void
    {
        $user = $this->makeStaff('never_used');
        DB::table('sessions')->insert(['id' => 'leftover', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $user->delete();

        $this->delete(route('users.force-destroy', $user))->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'leftover']);
    }

    // ---------- 登入限制與最後登入時間 ----------

    // 已停用與已刪除的帳號都不能登入。
    public function test_inactive_and_deleted_users_cannot_log_in(): void
    {
        auth()->logout();
        $inactive = $this->makeStaff('sleepy', 'technician', ['password' => 'pass-word-1', 'is_active' => false]);
        $deleted = $this->makeStaff('removed', 'technician', ['password' => 'pass-word-1']);
        $deleted->delete();

        $this->post(route('login'), ['login' => 'sleepy', 'password' => 'pass-word-1'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post(route('login'), ['login' => 'removed', 'password' => 'pass-word-1'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    // 用帳號名稱或 Email 都能登入，且成功登入會更新「最後登入時間」。
    public function test_login_by_username_or_email_updates_last_login_at(): void
    {
        auth()->logout();
        $user = $this->makeStaff('worker', 'technician', ['password' => 'pass-word-1', 'email' => 'worker@corp.test']);
        $this->assertNull($user->last_login_at);

        $this->post(route('login'), ['login' => 'worker', 'password' => 'pass-word-1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        auth()->logout();
        $this->post(route('login'), ['login' => 'worker@corp.test', 'password' => 'pass-word-1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
