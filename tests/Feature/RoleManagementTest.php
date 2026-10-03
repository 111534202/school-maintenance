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
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $admin;

    // 每個測試開始前先登入一位系統管理員。
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->loginAsAnyUser();
    }

    // 只有被勾選「身分主檔」權限的人進得去；沒勾的（例如資訊組主管）得到 403，連送出表單也不行。
    public function test_only_users_with_roles_manage_permission_can_open_the_role_master(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('roles.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => '駭客身分'])->assertForbidden();

        // 把「身分主檔」權限勾給一個自訂身分，擁有它的人就進得去。
        $this->actingAs($this->makeUserWithPermissions(['roles.manage']));
        $this->get(route('roles.index'))->assertOk();
    }

    // 列表顯示每個身分的名稱、人數與「已勾選 N 項權限」，並標示系統內建。
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

    // 管理員能新增自訂身分並勾選權限；自訂身分不是系統內建，代碼自動產生。
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

    // 新增時名稱不可重複；送來不存在的權限代碼會被擋下。
    public function test_create_validates_name_uniqueness_and_unknown_permissions(): void
    {
        $this->makeTechnician(); // 先讓「維修人員」這個身分名稱存在，才能測重複名稱

        $this->post(route('roles.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('roles.store'), ['name' => '維修人員'])->assertSessionHasErrors('name');
        $this->post(route('roles.store'), ['name' => '怪身分', 'permissions' => ['hack.the.planet']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => '怪身分']);
    }

    // 新增的身分會立刻出現在用戶表單的身分下拉選單。
    public function test_new_role_shows_up_in_the_user_form_dropdown(): void
    {
        $this->post(route('roles.store'), ['name' => '實習生', 'permissions' => ['repairs.create']]);

        // 用戶主檔的「身分權限」下拉選單直接從身分主檔抓。
        $this->get(route('users.create'))->assertOk()->assertSee('實習生');
    }

    // 修改權限後，操作紀錄會記下「新增了哪些、移除了哪些」。
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

    // 調整某身分的權限，屬於這個身分的人下一個動作就依新權限生效（不用重新登入）。
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

    // 系統管理員永遠擁有全部權限，就算送出取消勾選也沒用。
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

    // 編輯頁會依分組列出全部權限，並把目前已開放的打勾。
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

    // 系統內建身分不能刪除。
    public function test_system_roles_cannot_be_deleted(): void
    {
        $technician = $this->makeRole('technician', '維修人員');

        $this->delete(route('roles.destroy', $technician))->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $technician->id]);
    }

    // 還有用戶在用的身分不能刪；沒人用的自訂身分可以刪。
    public function test_role_in_use_cannot_be_deleted_but_unused_custom_role_can(): void
    {
        $inUse = $this->makeUserWithPermissions(['repairs.create'])->role;
        $this->delete(route('roles.destroy', $inUse))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $inUse->id]);

        $unused = $this->makeRole('custom_unused', '沒人用的身分', []);
        $this->delete(route('roles.destroy', $unused))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $unused->id]);
    }

    // 每個權限與分組都有中文與英文名稱（新增權限卻忘了補翻譯，這個測試會失敗提醒）。
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

    // Role::withPermission 只會找出「明確勾選」該權限的身分，不含隱含全開的系統管理員。
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
