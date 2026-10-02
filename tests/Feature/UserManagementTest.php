<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 用戶主檔（帳號管理）：權限、列表篩選、新增／編輯驗證、防呆規則、啟用停用、
 * 密碼重設、刪除還原，以及停用／刪除後不能登入。
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin('admin_main');
        $this->actingAs($this->admin);
    }

    private function makeAdmin(string $username, array $attrs = []): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => '系統管理員']);

        return User::factory()->create(['role_id' => $role->id, 'username' => $username] + $attrs);
    }

    private function makeStaff(string $username, string $roleSlug = 'technician', array $attrs = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);

        return User::factory()->create(['role_id' => $role->id, 'username' => $username] + $attrs);
    }

    private function validPayload(array $overrides = []): array
    {
        $role = Role::firstOrCreate(['slug' => 'technician'], ['name' => '維修人員']);

        return array_merge([
            'username' => 'new.user',
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

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();

        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_only_admin_role_can_open_user_master(): void
    {
        foreach (['it_manager', 'technician', 'teacher', 'executive'] as $slug) {
            $this->actingAs($this->makeStaff('u_' . $slug, $slug));

            $this->get(route('users.index'))->assertForbidden();
            $this->post(route('users.store'), $this->validPayload())->assertForbidden();
        }
    }

    // ---------- 列表與篩選 ----------

    public function test_index_lists_users_with_role_and_department(): void
    {
        $department = Department::create(['name' => '資訊組']);
        $this->makeStaff('wang', 'technician', ['name' => '王小明', 'department_id' => $department->id]);

        $response = $this->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('王小明');
        $response->assertSee('資訊組');
    }

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

    public function test_admin_can_create_a_user_and_password_is_hashed_and_not_logged(): void
    {
        $response = $this->post(route('users.store'), $this->validPayload());

        $response->assertRedirect(route('users.index'));
        $user = User::firstWhere('username', 'new.user');
        $this->assertNotNull($user);
        $this->assertSame('新同仁', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));

        $log = AuditLog::where('action', 'created')->where('loggable_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('secret-pass-1', json_encode($log->changes));
        $this->assertArrayNotHasKey('password', $log->changes);
    }

    public function test_create_validates_required_unique_and_format_rules(): void
    {
        $this->makeStaff('taken', 'technician', ['email' => 'taken@example.com']);

        $this->post(route('users.store'), $this->validPayload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
        $this->post(route('users.store'), $this->validPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
        $this->post(route('users.store'), $this->validPayload(['username' => 'bad name!']))
            ->assertSessionHasErrors('username');
        $this->post(route('users.store'), $this->validPayload(['username' => 'ab']))
            ->assertSessionHasErrors('username');
        $this->post(route('users.store'), $this->validPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->validPayload(['password_confirmation' => 'different-pass']))
            ->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->validPayload(['role_id' => 99999]))
            ->assertSessionHasErrors('role_id');
        $this->post(route('users.store'), $this->validPayload(['name' => '', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseMissing('users', ['username' => 'new.user']);
    }

    // ---------- 編輯 ----------

    public function test_admin_can_update_a_user_and_keep_own_unique_values(): void
    {
        $user = $this->makeStaff('editme', 'technician', ['name' => '舊名', 'email' => 'old@example.com']);
        $department = Department::create(['name' => '教務處']);

        $response = $this->put(route('users.update', $user), $this->validPayload([
            'username' => 'editme',          // 沒改帳號：不能跟自己衝突
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

    public function test_update_does_not_change_the_password(): void
    {
        $user = $this->makeStaff('keeppass', 'technician', ['password' => 'original-pass']);

        $this->put(route('users.update', $user), $this->validPayload([
            'username' => 'keeppass', 'email' => $user->email, 'password' => 'hacked-pass1', 'password_confirmation' => 'hacked-pass1',
        ]))->assertRedirect();

        $this->assertTrue(Hash::check('original-pass', $user->fresh()->password));
    }

    // ---------- 防呆規則 ----------

    public function test_admin_cannot_demote_or_deactivate_self(): void
    {
        $techRole = Role::firstOrCreate(['slug' => 'technician'], ['name' => '維修人員']);

        $this->put(route('users.update', $this->admin), $this->validPayload([
            'username' => 'admin_main', 'email' => $this->admin->email, 'role_id' => $techRole->id,
        ]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->isAdmin());

        $this->put(route('users.update', $this->admin), $this->validPayload([
            'username' => 'admin_main', 'email' => $this->admin->email, 'role_id' => $this->admin->role_id, 'is_active' => '0',
        ]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_admin_cannot_toggle_or_delete_self(): void
    {
        $this->patch(route('users.toggle', $this->admin))->assertSessionHas('error');
        $this->delete(route('users.destroy', $this->admin))->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertFalse($this->admin->fresh()->trashed());
    }

    public function test_last_active_admin_cannot_be_deleted_deactivated_or_demoted(): void
    {
        // 操作者是一位「已停用」的管理員（用 actingAs 繞過登入限制），
        // 所以被操作的 target 就是系統裡唯一一位啟用中的管理員。
        // setUp 建立的 admin_main 也先停用，這樣 only_admin 才真的是唯一一位啟用中的管理員。
        $this->admin->update(['is_active' => false]);
        $actor = $this->makeAdmin('inactive_actor', ['is_active' => false]);
        $target = $this->makeAdmin('only_admin');
        $this->actingAs($actor);

        $this->delete(route('users.destroy', $target))->assertSessionHas('error');
        $this->assertFalse($target->fresh()->trashed());

        $this->patch(route('users.toggle', $target))->assertSessionHas('error');
        $this->assertTrue($target->fresh()->is_active);

        $techRole = Role::firstOrCreate(['slug' => 'technician'], ['name' => '維修人員']);
        $this->put(route('users.update', $target), $this->validPayload([
            'username' => 'only_admin', 'email' => $target->email, 'role_id' => $techRole->id,
        ]))->assertSessionHas('error');
        $this->assertTrue($target->fresh()->isAdmin());
    }

    public function test_an_admin_can_be_removed_when_another_active_admin_exists(): void
    {
        $other = $this->makeAdmin('second_admin');

        $this->delete(route('users.destroy', $other))->assertSessionHas('success');

        $this->assertTrue($other->fresh()->trashed());
    }

    // ---------- 啟用／停用 ----------

    public function test_toggle_deactivates_and_reactivates_and_logs_the_change(): void
    {
        $user = $this->makeStaff('toggle_me');

        $this->patch(route('users.toggle', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->is_active);

        $this->patch(route('users.toggle', $user))->assertSessionHas('success');
        $this->assertTrue($user->fresh()->is_active);

        $this->assertSame(2, AuditLog::where('action', 'status_changed')->where('loggable_id', $user->id)->count());
    }

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

    public function test_delete_is_soft_and_can_be_restored(): void
    {
        $user = $this->makeStaff('soft_del');

        $this->delete(route('users.destroy', $user))->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->patch(route('users.restore', $user->id))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'restored', 'loggable_id' => $user->id]);
    }

    // ---------- 登入限制與最後登入時間 ----------

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
