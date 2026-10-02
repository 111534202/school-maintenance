<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 用戶主檔的示範資料：多幾位不同角色、不同部門的用戶，另外放一位「已停用」、
 * 一位「已刪除」的帳號，展示列表篩選（角色／部門／狀態）與還原功能時才有東西可看。
 * 密碼統一是 password。必須在 RoleSeeder、UserSeeder、DepartmentSeeder 之後執行。
 */
class UserDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::pluck('id', 'name');
        $roles = Role::pluck('id', 'slug');

        $demoUsers = [
            ['username' => 'chen.dacheng', 'name' => '陳大成', 'role' => 'technician', 'department' => '總務處', 'phone' => '0911-111-001'],
            ['username' => 'wang.xiaoming', 'name' => '王小明', 'role' => 'technician', 'department' => '資訊工程系', 'phone' => '0911-111-002'],
            ['username' => 'liu.xiaohua', 'name' => '劉小華', 'role' => 'technician', 'department' => '電機工程系', 'phone' => '0911-111-003'],
            ['username' => 'lin.laoshi', 'name' => '林老師', 'role' => 'teacher', 'department' => '資訊工程系', 'phone' => '0922-222-001'],
            ['username' => 'huang.laoshi', 'name' => '黃老師', 'role' => 'teacher', 'department' => '通識教育中心', 'phone' => '0922-222-002'],
            ['username' => 'zhang.zhuren', 'name' => '張主任', 'role' => 'executive', 'department' => '總務處', 'phone' => '0933-333-001'],
        ];

        foreach ($demoUsers as $demo) {
            $this->upsert($demo, $roles, $departments, true);
        }

        // 已停用：帳號保留但不能登入。
        $this->upsert(
            ['username' => 'retired.user', 'name' => '李離職', 'role' => 'technician', 'department' => '總務處', 'phone' => null],
            $roles, $departments, false
        );

        // 已刪除（軟刪除）：預設列表看不到，用「已刪除」篩選才找得到，可以還原。
        $deleted = $this->upsert(
            ['username' => 'deleted.user', 'name' => '周已刪', 'role' => 'teacher', 'department' => '電機工程系', 'phone' => null],
            $roles, $departments, true
        );
        $deleted->delete();
    }

    private function upsert(array $demo, $roles, $departments, bool $isActive): User
    {
        $user = User::withTrashed()->where('username', $demo['username'])->first() ?? new User();

        $user->fill([
            'username' => $demo['username'],
            'name' => $demo['name'],
            'email' => $demo['username'] . '@school.test',
            'phone' => $demo['phone'],
            'role_id' => $roles[$demo['role']] ?? null,
            'department_id' => $departments[$demo['department']] ?? null,
            'is_active' => $isActive,
            'password' => 'password',
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
