<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Major;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 11/23 劉家芸提出：身分主檔依開放的權限數量多到少；部門、科系主檔依代碼 A→Z（代碼不可重複）。
 */
class MasterListSortTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_roles_are_sorted_by_how_many_permissions_they_have(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        Role::create(['name' => '少權限', 'role_code' => 'few', 'permissions' => ['repairs.create']]);
        Role::create(['name' => '多權限', 'role_code' => 'many', 'permissions' => ['repairs.create', 'repairs.dispatch', 'repairs.accept']]);

        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSeeInOrder(['系統管理員', '多權限', '少權限']);
    }

    public function test_departments_and_majors_are_sorted_by_code(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        Department::create(['name' => '乙部門', 'department_code' => 'ZZ', 'is_active' => true]);
        Department::create(['name' => '甲部門', 'department_code' => 'AA', 'is_active' => true]);
        Department::create(['name' => '沒代碼部門', 'department_code' => null, 'is_active' => true]);
        Major::create(['name' => '丙科系', 'major_code' => 'MZ', 'abbreviation' => '丙', 'grade_count' => 4, 'class_count' => 1, 'is_active' => true]);
        Major::create(['name' => '丁科系', 'major_code' => 'MA', 'abbreviation' => '丁', 'grade_count' => 4, 'class_count' => 1, 'is_active' => true]);

        $this->actingAs($admin)->get(route('departments.index'))->assertOk()->assertSeeInOrder(['甲部門', '乙部門', '沒代碼部門']);
        $this->actingAs($admin)->get(route('majors.index'))->assertOk()->assertSeeInOrder(['丁科系', '丙科系']);
    }

    public function test_codes_cannot_repeat(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        Department::create(['name' => '甲部門', 'department_code' => 'AA', 'is_active' => true]);
        Major::create(['name' => '丁科系', 'major_code' => 'MA', 'abbreviation' => '丁', 'grade_count' => 4, 'class_count' => 1, 'is_active' => true]);

        $this->actingAs($admin)->post(route('departments.store'), ['name' => '新部門', 'department_code' => 'AA', 'is_active' => 1])->assertSessionHasErrors('department_code');
        $this->actingAs($admin)->post(route('majors.store'), ['name' => '新科系', 'major_code' => 'MA', 'abbreviation' => '新', 'grade_count' => 4, 'class_count' => 1, 'is_active' => 1])->assertSessionHasErrors('major_code');
    }
}
