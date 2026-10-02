<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;

/**
 * 測試共用小工具：報修模組的路由都需要登入，而且每個動作都由「身分主檔」勾選的權限控制。
 * 這個 trait 統一提供「建立某個身分的使用者」的寫法，內建身分會帶入跟正式環境一樣的預設權限，
 * 避免每個測試檔案各自重複同樣的建立邏輯。
 */
trait InteractsWithRolesAndUsers
{
    /** 建立（或取得）一個身分；內建身分（admin、technician…）自動套用預設權限，也可以自己指定權限。 */
    protected function makeRole(string $slug, string $name, ?array $permissions = null): Role
    {
        return Role::firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'is_system' => in_array($slug, ['admin', 'it_manager', 'technician', 'teacher', 'executive'], true),
            'permissions' => $permissions ?? PermissionCatalog::defaultsFor($slug),
        ]);
    }

    protected function makeUserWithRole(string $slug, string $name, array $attrs = []): User
    {
        return User::factory()->create(['role_id' => $this->makeRole($slug, $name)->id] + $attrs);
    }

    /** 建立一個「只有指定權限」的自訂身分，並建立擁有這個身分的使用者。 */
    protected function makeUserWithPermissions(array $permissions, array $attrs = []): User
    {
        $role = Role::create([
            'name' => 'custom ' . uniqid(),
            'slug' => 'custom_' . uniqid(),
            'is_system' => false,
            'permissions' => $permissions,
        ]);

        return User::factory()->create(['role_id' => $role->id] + $attrs);
    }

    protected function makeTechnician(string $name = '測試維修人員'): User
    {
        return $this->makeUserWithRole('technician', '維修人員');
    }

    protected function makeItManager(): User
    {
        return $this->makeUserWithRole('it_manager', '資訊組主管');
    }

    /** 登入一個系統管理員（永遠擁有全部權限），供不在意權限細節、只需要「能操作」的測試使用。 */
    protected function loginAsAnyUser(): User
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');
        $this->actingAs($user);

        return $user;
    }
}
