<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 教室主檔：權限、新增／修改／啟用停用、驗證與篩選（教室不提供刪除，只能停用）。 */
class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->loginAsAnyUser();
    }

    /** 輔助方法：一份完全合法的教室表單內容，各測試只改自己要測的欄位。 */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F',
            'room_code' => 'A101', 'room_name' => 'A101 電腦教室', 'room_type' => '電腦教室',
        ], $overrides);
    }

    private function makeClassroom(string $code = 'A101', array $attrs = []): Classroom
    {
        return Classroom::create(array_merge([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'room_code' => $code, 'room_name' => "$code 教室",
        ], $attrs));
    }

    // 只有被勾選「教室主檔」權限的人進得去：維修人員 403，資訊組主管（預設有勾）可以。
    public function test_only_users_with_classrooms_manage_permission_can_open_it(): void
    {
        $this->actingAs($this->makeTechnician());
        $this->get(route('classrooms.index'))->assertForbidden();
        $this->post(route('classrooms.store'), $this->payload())->assertForbidden();

        $this->actingAs($this->makeItManager());
        $this->get(route('classrooms.index'))->assertOk();
    }

    // 教室只能停用，沒有刪除功能（歷史的設備與報修資料才不會失去所屬教室）。
    public function test_there_is_no_delete_route(): void
    {
        $this->assertFalse(Route::has('classrooms.destroy'));
    }

    public function test_admin_can_create_a_classroom_and_it_is_logged(): void
    {
        $department = Department::create(['name' => '資訊組']);

        $this->post(route('classrooms.store'), $this->payload(['department_id' => $department->id, 'manager_id' => $this->admin->id]))
            ->assertRedirect(route('classrooms.index'))->assertSessionHas('success');

        $classroom = Classroom::firstWhere('room_code', 'A101');
        $this->assertSame($department->id, $classroom->department_id);
        $this->assertSame($this->admin->id, $classroom->manager_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $classroom->id, 'loggable_type' => Classroom::class]);
    }

    // 部門與管理人可以不選；必填欄位沒填、教室代碼重複、部門或管理人不存在都會被擋下。
    public function test_create_validates_required_unique_and_existing_references(): void
    {
        $this->makeClassroom('A101');

        $this->post(route('classrooms.store'), $this->payload(['room_code' => 'A101']))->assertSessionHasErrors('room_code');
        $this->post(route('classrooms.store'), $this->payload(['room_code' => 'B1', 'campus' => '']))->assertSessionHasErrors('campus');
        $this->post(route('classrooms.store'), $this->payload(['room_code' => 'B2', 'department_id' => 99999]))->assertSessionHasErrors('department_id');
        $this->post(route('classrooms.store'), $this->payload(['room_code' => 'B3', 'manager_id' => 99999]))->assertSessionHasErrors('manager_id');

        $this->post(route('classrooms.store'), $this->payload(['room_code' => 'OK1']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('classrooms', ['room_code' => 'OK1', 'department_id' => null, 'manager_id' => null]);
    }

    // 修改時，不改教室代碼不會被誤判成「與自己重複」，欄位確實被更新。
    public function test_update_keeps_own_room_code_and_changes_fields(): void
    {
        $classroom = $this->makeClassroom('A101');

        $this->put(route('classrooms.update', $classroom), $this->payload(['room_code' => 'A101', 'room_name' => '新名稱']))
            ->assertRedirect(route('classrooms.index'))->assertSessionHasNoErrors();

        $this->assertSame('新名稱', $classroom->fresh()->room_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'loggable_id' => $classroom->id, 'loggable_type' => Classroom::class]);
    }

    public function test_update_cannot_take_another_classrooms_code(): void
    {
        $this->makeClassroom('A101');
        $other = $this->makeClassroom('A102');

        $this->put(route('classrooms.update', $other), $this->payload(['room_code' => 'A101']))->assertSessionHasErrors('room_code');
        $this->assertSame('A102', $other->fresh()->room_code);
    }

    // 啟用／停用切換有效並寫進操作紀錄。
    public function test_toggle_deactivates_and_reactivates(): void
    {
        $classroom = $this->makeClassroom('A101');
        $this->assertTrue($classroom->fresh()->is_active);

        $this->patch(route('classrooms.toggle', $classroom))->assertSessionHas('success');
        $this->assertFalse($classroom->fresh()->is_active);
        $this->patch(route('classrooms.toggle', $classroom));
        $this->assertTrue($classroom->fresh()->is_active);
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'status_changed')->where('loggable_id', $classroom->id)->count());
    }

    public function test_index_can_filter_by_keyword_department_and_status(): void
    {
        $dept = Department::create(['name' => '電機系']);
        $this->makeClassroom('EE-201', ['room_name' => '電機實習教室', 'department_id' => $dept->id]);
        $this->makeClassroom('IT-301', ['room_name' => '資訊教室', 'is_active' => false]);

        $this->get(route('classrooms.index', ['keyword' => '電機']))->assertSee('EE-201')->assertDontSee('IT-301');
        $this->get(route('classrooms.index', ['keyword' => 'IT-3']))->assertSee('IT-301')->assertDontSee('EE-201');
        $this->get(route('classrooms.index', ['department_id' => $dept->id]))->assertSee('EE-201')->assertDontSee('IT-301');
        $this->get(route('classrooms.index', ['is_active' => '0']))->assertSee('IT-301')->assertDontSee('EE-201');
        $this->get(route('classrooms.index', ['is_active' => '1']))->assertSee('EE-201')->assertDontSee('IT-301');
    }

    // 新增與編輯表單的部門下拉選單只列啟用中的部門，但保留這間教室目前已選的。
    public function test_form_dropdown_lists_active_departments_and_keeps_the_current_one(): void
    {
        $active = Department::create(['name' => '啟用部門']);
        $inactive = Department::create(['name' => '停用部門', 'is_active' => false]);
        $classroom = $this->makeClassroom('A101', ['department_id' => $inactive->id]);

        $this->get(route('classrooms.create'))->assertSee('啟用部門')->assertDontSee('停用部門');
        $this->get(route('classrooms.edit', $classroom))->assertSee('啟用部門')->assertSee('停用部門');
    }
}
