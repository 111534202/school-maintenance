<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 部門主檔：新增／修改／停用／刪除，以及用戶、教室下拉選單從這裡抓。 */
class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
    }

    private function makeClassroom(?Department $department, string $code = 'R101'): Classroom
    {
        return Classroom::create([
            'department_id' => $department?->id, 'campus' => '本校區', 'building' => 'A棟',
            'floor' => '1', 'room_code' => $code, 'room_name' => $code . ' 教室',
        ]);
    }

    public function test_only_users_with_departments_manage_permission_can_open_it(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('departments.index'))->assertForbidden();

        $this->actingAs($this->makeUserWithPermissions(['departments.manage']));
        $this->get(route('departments.index'))->assertOk();
    }

    public function test_admin_can_create_a_department_with_code_and_it_is_logged(): void
    {
        $this->post(route('departments.store'), [
            'name' => '研發處', 'code' => 'RD', 'description' => '產品研發', 'is_active' => '1',
        ])->assertRedirect(route('departments.index'));

        $department = Department::firstWhere('code', 'RD');
        $this->assertNotNull($department);
        $this->assertTrue($department->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $department->id]);
    }

    public function test_create_validates_unique_name_unique_code_and_code_format(): void
    {
        Department::create(['name' => '總務處', 'code' => 'GA']);

        $this->post(route('departments.store'), ['name' => '總務處', 'is_active' => '1'])->assertSessionHasErrors('name');
        $this->post(route('departments.store'), ['name' => '新部門', 'code' => 'GA', 'is_active' => '1'])->assertSessionHasErrors('code');
        $this->post(route('departments.store'), ['name' => '新部門', 'code' => 'bad code!', 'is_active' => '1'])->assertSessionHasErrors('code');
        $this->post(route('departments.store'), ['name' => '', 'is_active' => '1'])->assertSessionHasErrors('name');
    }

    public function test_update_keeps_own_unique_values_and_changes_fields(): void
    {
        $department = Department::create(['name' => '人事室', 'code' => 'HR']);

        $this->put(route('departments.update', $department), [
            'name' => '人力資源室', 'code' => 'HR', 'description' => '改名', 'is_active' => '1',
        ])->assertRedirect(route('departments.index'));

        $this->assertSame('人力資源室', $department->fresh()->name);
    }

    public function test_toggle_deactivates_and_reactivates(): void
    {
        $department = Department::create(['name' => '會計室']);

        $this->patch(route('departments.toggle', $department))->assertSessionHas('success');
        $this->assertFalse($department->fresh()->is_active);

        $this->patch(route('departments.toggle', $department))->assertSessionHas('success');
        $this->assertTrue($department->fresh()->is_active);
    }

    public function test_unused_department_can_be_deleted(): void
    {
        $department = Department::create(['name' => '空部門']);

        $this->delete(route('departments.destroy', $department))->assertSessionHas('success');

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_department_with_users_or_classrooms_cannot_be_deleted(): void
    {
        $withUser = Department::create(['name' => '有人的部門']);
        User::factory()->create(['department_id' => $withUser->id]);
        $withRoom = Department::create(['name' => '有教室的部門']);
        $this->makeClassroom($withRoom);

        $this->delete(route('departments.destroy', $withUser))->assertSessionHas('error');
        $this->delete(route('departments.destroy', $withRoom))->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $withUser->id]);
        $this->assertDatabaseHas('departments', ['id' => $withRoom->id]);
    }

    public function test_index_shows_counts_and_can_filter(): void
    {
        $department = Department::create(['name' => '資訊中心', 'code' => 'IT']);
        User::factory()->count(2)->create(['department_id' => $department->id]);
        Department::create(['name' => '停用的處室', 'is_active' => false]);

        $this->get(route('departments.index'))->assertSee('資訊中心')->assertSee('停用的處室');
        $this->get(route('departments.index', ['keyword' => 'IT']))->assertSee('資訊中心')->assertDontSee('停用的處室');
        $this->get(route('departments.index', ['status' => 'inactive']))->assertSee('停用的處室')->assertDontSee('資訊中心');
    }

    public function test_user_form_dropdown_reads_active_departments_and_keeps_the_current_one(): void
    {
        Department::create(['name' => '啟用部門甲']);
        $inactive = Department::create(['name' => '停用部門乙', 'is_active' => false]);
        $user = User::factory()->create(['department_id' => $inactive->id, 'username' => 'someone']);

        $this->get(route('users.create'))->assertSee('啟用部門甲')->assertDontSee('停用部門乙');

        // 編輯一個本來就隸屬已停用部門的人：他目前的部門要保留在選項裡，不然儲存時會被默默清掉。
        $this->get(route('users.edit', $user))->assertSee('啟用部門甲')->assertSee('停用部門乙');
    }

    public function test_classroom_form_dropdown_reads_active_departments(): void
    {
        Department::create(['name' => '啟用部門丙']);
        Department::create(['name' => '停用部門丁', 'is_active' => false]);

        $this->get(route('classrooms.create'))->assertSee('啟用部門丙')->assertDontSee('停用部門丁');
    }

    public function test_new_department_appears_in_the_user_form_right_away(): void
    {
        $this->post(route('departments.store'), ['name' => '剛新增的部門', 'is_active' => '1']);

        $this->get(route('users.create'))->assertSee('剛新增的部門');
    }
}
