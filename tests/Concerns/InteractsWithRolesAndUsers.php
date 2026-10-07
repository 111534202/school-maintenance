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
        // firstOrCreate：同一個身分代碼已經建立過就直接拿來用，不重複建立。
        return Role::firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'is_system' => in_array($slug, ['admin', 'it_manager', 'technician', 'teacher', 'executive'], true),
            'permissions' => $permissions ?? PermissionCatalog::defaultsFor($slug),
        ]);
    }

    // 建立一位擁有指定身分的用戶（姓名、Email 由工廠隨機產生，$attrs 可覆蓋）。
    protected function makeUserWithRole(string $slug, string $name, array $attrs = []): User
    {
        // 用工廠建立一個假用戶，身分設成指定的身分；$attrs 可覆蓋任何欄位（例如帳號、姓名）。
        return User::factory()->create(['role_id' => $this->makeRole($slug, $name)->id] + $attrs);
    }

    /** 建立一個「只有指定權限」的自訂身分，並建立擁有這個身分的使用者。 */
    protected function makeUserWithPermissions(array $permissions, array $attrs = []): User
    {
        // 建立一個「只有指定權限」的自訂身分（代碼用 uniqid 產生，確保每次都不重複）。
        $role = Role::create([
            'name' => 'custom ' . uniqid(),
            'slug' => 'custom_' . uniqid(),
            'is_system' => false,
            'permissions' => $permissions,
        ]);

        return User::factory()->create(['role_id' => $role->id] + $attrs);
    }

    // 建立一位維修人員（可被指派處理案件）。
    protected function makeTechnician(string $name = '測試維修人員'): User
    {
        // 維修人員：內建身分，預設權限是提出報修、處理維修、可被指派。
        return $this->makeUserWithRole('technician', '維修人員');
    }

    // 建立一位資訊組主管（有設備主檔、派工、接收派工副本等權限）。
    protected function makeItManager(): User
    {
        // 資訊組主管：內建身分，預設有設備、教室主檔、派工、操作紀錄與知識庫管理等權限。
        return $this->makeUserWithRole('it_manager', '資訊組主管');
    }

    /** 登入一個系統管理員（永遠擁有全部權限），供不在意權限細節、只需要「能操作」的測試使用。 */
    protected function loginAsAnyUser(): User
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');
        // actingAs：讓接下來的測試請求都以這位用戶「已登入」的身分執行（不必真的走登入畫面）。
        $this->actingAs($user);

        return $user;
    }
}
