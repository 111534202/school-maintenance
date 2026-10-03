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
    // 執行這個 Seeder：建立示範用戶（含一位已停用、一位已刪除）。
    public function run(): void
    {
        // 取出「部門名稱 → 部門編號」對照表，下面用名稱找編號。
        $departments = Department::pluck('id', 'name');
        // 取出「身分代碼 → 身分編號」對照表。
        $roles = Role::pluck('id', 'slug');

        // 示範用戶清單：帳號、姓名、身分代碼、部門名稱、電話。
        $demoUsers = [
            ['username' => 'chen.dacheng', 'name' => '陳大成', 'role' => 'technician', 'department' => '總務處', 'phone' => '0911-111-001'],
            ['username' => 'wang.xiaoming', 'name' => '王小明', 'role' => 'technician', 'department' => '資訊工程系', 'phone' => '0911-111-002'],
            ['username' => 'liu.xiaohua', 'name' => '劉小華', 'role' => 'technician', 'department' => '電機工程系', 'phone' => '0911-111-003'],
            ['username' => 'lin.laoshi', 'name' => '林老師', 'role' => 'teacher', 'department' => '資訊工程系', 'phone' => '0922-222-001'],
            ['username' => 'huang.laoshi', 'name' => '黃老師', 'role' => 'teacher', 'department' => '通識教育中心', 'phone' => '0922-222-002'],
            ['username' => 'zhang.zhuren', 'name' => '張主任', 'role' => 'executive', 'department' => '總務處', 'phone' => '0933-333-001'],
        ];

        // 逐一建立，最後一個參數 true 代表「啟用中」。
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
        // 把這位標記為已刪除（軟刪除：資料還在，只是列表預設看不到）。
        $deleted->delete();
    }

    // 共用的小工具：帳號已存在就更新，不存在就新增（upsert = update + insert），重跑 Seeder 不會產生重複用戶。
    private function upsert(array $demo, $roles, $departments, bool $isActive): User
    {
        // withTrashed：連已被軟刪除的帳號也要找，才不會對同一個帳號重複新增。
        $user = User::withTrashed()->where('username', $demo['username'])->first() ?? new User();

        $user->fill([
            'username' => $demo['username'],
            'name' => $demo['name'],
            // 示範用的假信箱：帳號名稱 + @school.test。
            'email' => $demo['username'] . '@school.test',
            'phone' => $demo['phone'],
            'role_id' => $roles[$demo['role']] ?? null,
            // 用部門名稱查出部門編號；找不到就留空。
            'department_id' => $departments[$demo['department']] ?? null,
            // 是否啟用：false 的就是「已停用」的示範帳號。
            'is_active' => $isActive,
            'password' => 'password',
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
