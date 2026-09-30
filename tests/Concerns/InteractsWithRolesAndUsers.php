<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;

/**
 * 測試共用小工具：devices/users/roles 表合併進來後，報修模組的路由都需要登入，
 * 派工/重新指派也改成挑選真正的 users（角色 = 維修人員）。這個 trait 統一提供
 * 「建立一個角色使用者」的寫法，避免每個測試檔案各自重複同樣的建立邏輯。
 */
trait InteractsWithRolesAndUsers
{
    protected function makeUserWithRole(string $slug, string $name): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeTechnician(string $name = '測試維修人員'): User
    {
        return $this->makeUserWithRole('technician', '維修人員');
    }

    protected function makeItManager(): User
    {
        return $this->makeUserWithRole('it_manager', '資訊組主管');
    }

    /** 登入一個任意角色的使用者，供只需要「有登入」就好、不在意角色的測試使用。 */
    protected function loginAsAnyUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
