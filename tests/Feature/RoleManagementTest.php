<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 身分主檔：像 Discord 身分組，新增身分後勾選要開放的權限。 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->loginAsAnyUser();
    }

    public function test_only_users_with_roles_manage_permission_can_open_the_role_master(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('roles.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => '駭客身分'])->assertForbidden();

        // 把「身分主檔」權限勾給一個自訂身分，擁有它的人就進得去。
        $this->actingAs($this->makeUserWithPermissions(['roles.manage']));
        $this->get(route('roles.index'))->assertOk();
    }

    public function test_index_lists_roles_with_user_count_and_permission_count(): void
    {
        $this->makeTechnician();

        $this->get(route('roles.index'))
            ->assertOk()
            ->assertSee('系統管理員')
            ->assertSee('全部權限')
            ->assertSee('維修人員')
            ->assertSee('3 項權限')
            ->assertSee('系統內建');
    }

    public function test_admin_can_create_a_custom_role_with_ticked_permissions(): void
    {
        $response = $this->post(route('roles.store'), [
            'name' => '設備組長',
            'description' => '管理設備與教室',
            'permissions' => ['devices.manage', 'classrooms.manage', 'repairs.create'],
        ]);

        $response->assertRedirect(route('roles.index'));
        $role = Role::firstWhere('name', '設備組長');
        $this->assertNotNull($role);
        $this->assertFalse($role->is_system);
        $this->assertStringStartsWith('custom_', $role->slug);
        $this->assertEqualsCanonicalizing(['devices.manage', 'classrooms.manage', 'repairs.create'], $role->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $role->id]);
    }

    public function test_create_validates_name_uniqueness_and_unknown_permissions(): void
    {
        $this->makeTechnician(); // 先讓「維修人員」這個身分名稱存在，才能測重複名稱

        $this->post(route('roles.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('roles.store'), ['name' => '維修人員'])->assertSessionHasErrors('name');
        $this->post(route('roles.store'), ['name' => '怪身分', 'permissions' => ['hack.the.planet']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => '怪身分']);
    }

    public function test_new_role_shows_up_in_the_user_form_dropdown(): void
    {
        $this->post(route('roles.store'), ['name' => '實習生', 'permissions' => ['repairs.create']]);

        // 用戶主檔的「身分權限」下拉選單直接從身分主檔抓。
        $this->get(route('users.create'))->assertOk()->assertSee('實習生');
    }

    public function test_update_changes_permissions_and_logs_what_was_added_and_removed(): void
    {
        $role = $this->makeRole('custom_x', '自訂身分', ['repairs.create', 'repairs.accept']);

        $this->put(route('roles.update', $role), [
            'name' => '自訂身分改名',
            'description' => '',
            'permissions' => ['repairs.create', 'repairs.dispatch'],
        ])->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('自訂身分改名', $role->name);
        $this->assertEqualsCanonicalizing(['repairs.create', 'repairs.dispatch'], $role->permissions);

        $log = AuditLog::where('action', 'updated')->where('loggable_id', $role->id)->first();
        $this->assertSame(['repairs.dispatch'], $log->changes['permissions_added']);
        $this->assertSame(['repairs.accept'], $log->changes['permissions_removed']);
    }

    public function test_permission_changes_take_effect_immediately_for_users_of_that_role(): void
    {
        $user = $this->makeUserWithPermissions(['repairs.create']);
        $role = $user->role;

        $this->actingAs($user);
        $this->get(route('repairs.create'))->assertOk();
        $this->get(route('classrooms.index'))->assertForbidden();

        $this->actingAs($this->admin);
        $this->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => ['classrooms.manage']]);

        $this->actingAs($user->fresh());
        $this->get(route('classrooms.index'))->assertOk();
        $this->get(route('repairs.create'))->assertForbidden();
    }

    public function test_admin_role_always_keeps_every_permission(): void
    {
        $adminRole = $this->admin->role;

        $this->put(route('roles.update', $adminRole), ['name' => '超級管理員', 'permissions' => []])
            ->assertRedirect(route('roles.index'));

        $adminRole->refresh();
        $this->assertSame('超級管理員', $adminRole->name);                       // 名稱可以改
        $this->assertEqualsCanonicalizing(PermissionCatalog::all(), $adminRole->effectivePermissions()); // 權限改不掉
        $this->get(route('roles.index'))->assertOk();                          // 管理員自己沒被鎖在門外
    }

    public function test_edit_page_shows_all_permissions_grouped_and_ticked(): void
    {
        $role = $this->makeRole('custom_y', '自訂 Y', ['devices.manage']);

        $response = $this->get(route('roles.edit', $role));

        $response->assertOk();
        foreach (['主檔管理', '報修與維修', '自助知識庫', '設備主檔', '可被指派為維修人員', '接收派工通知副本', '管理知識庫'] as $text) {
            $response->assertSee($text);
        }
        $response->assertSee('value="devices.manage"', false);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $technician = $this->makeRole('technician', '維修人員');

        $this->delete(route('roles.destroy', $technician))->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $technician->id]);
    }

    public function test_role_in_use_cannot_be_deleted_but_unused_custom_role_can(): void
    {
        $inUse = $this->makeUserWithPermissions(['repairs.create'])->role;
        $this->delete(route('roles.destroy', $inUse))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $inUse->id]);

        $unused = $this->makeRole('custom_unused', '沒人用的身分', []);
        $this->delete(route('roles.destroy', $unused))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $unused->id]);
    }

    public function test_every_permission_and_group_has_a_chinese_and_english_label(): void
    {
        // 新增權限卻忘了補翻譯的話，畫面上會直接顯示 permissions.items.xxx 這種代碼，這個測試會先擋下來。
        foreach (['zh_TW', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (array_keys(PermissionCatalog::GROUPS) as $group) {
                $key = 'permissions.groups.' . $group;
                $this->assertNotSame($key, __($key), "[$locale] 缺少權限分組翻譯：$key");
            }
            foreach (PermissionCatalog::all() as $permission) {
                foreach (['name', 'description'] as $field) {
                    $key = "permissions.items.$permission.$field";
                    $this->assertNotSame($key, __($key), "[$locale] 缺少權限翻譯：$key");
                }
            }
        }
        app()->setLocale('zh_TW');
    }

    public function test_with_permission_scope_only_matches_explicitly_ticked_roles(): void
    {
        $this->makeRole('technician', '維修人員');      // 預設有 repairs.assignable
        $this->makeRole('teacher', '教師');             // 沒有

        $slugs = Role::withPermission('repairs.assignable')->pluck('slug')->all();

        $this->assertContains('technician', $slugs);
        $this->assertNotContains('teacher', $slugs);
        $this->assertNotContains('admin', $slugs); // 管理員的「全開」是隱含的，不算勾選
    }
}
