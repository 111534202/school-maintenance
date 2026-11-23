<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Database\Factories\RepairRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 內建的「學生」身分：只能借教室（空間預約）與提出報修，沒有其他功能。
 */
class StudentRoleTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_the_student_role_exists_after_migrating_with_only_the_report_permission(): void
    {
        $role = Role::where('role_code', 'student')->first();

        $this->assertNotNull($role, 'migration 應該建立學生身分');
        $this->assertTrue($role->is_system, '學生是系統內建身分，不能被刪除');
        $this->assertSame(['repairs.create'], $role->permissions);   // 只能報修（借教室不需要權限），不能驗收
        $this->assertEqualsCanonicalizing(PermissionCatalog::defaultsFor('student'), $role->permissions);

        // 重跑 migration 不會重複建立。
        (include base_path('database/migrations/2026_11_23_100000_add_student_role.php'))->up();
        $this->assertSame(1, Role::where('role_code', 'student')->count());
    }

    public function test_a_student_can_open_reporting_and_reservation_pages_and_nothing_else(): void
    {
        $student = $this->makeUserWithRole('student', '學生');
        $this->actingAs($student);

        // 能用的：主控台、報修（看板、新增）、空間預約、知識庫。
        foreach (['dashboard', 'repairs.index', 'repairs.create', 'reservations.index', 'reservations.create', 'knowledge-base.index'] as $name) {
            $this->get(route($name))->assertOk();
        }

        // 其他功能一律不行（403）：主檔、保養、AI、零件、成本、操作紀錄、備份、寄信設定、匯入。
        $forbidden = [
            route('users.index'), route('roles.index'), route('departments.index'), route('majors.index'),
            route('classrooms.index'), route('device-categories.index'), route('devices.index'),
            route('maintenance-items.index'), route('maintenance-plans.index'), route('maintenance-orders.index'), route('device-profile.index'),
            route('maintenance-completion-rate.index'), route('preventive-candidates.index'), route('ai-settings.edit'),
            route('parts.index'), route('repair-cost-analysis.index'),
            route('audit-logs.index'), route('backups.index'), route('mail-settings.edit'), route('imports.index', ['type' => 'devices']),
        ];
        foreach ($forbidden as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_student_can_report_but_cannot_dispatch_or_handle_repairs(): void
    {
        $student = $this->makeUserWithRole('student', '學生');
        $technician = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'pending', 'requester_id' => $student->id]);
        $others = RepairRequestFactory::new()->create(['status' => 'pending']);

        $this->actingAs($student)
            ->post(route('repairs.store'), ['title' => '教室燈壞了', 'description' => '第三排的燈不亮', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_note' => '三樓教室的燈'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('repair_requests', ['title' => '教室燈壞了', 'requester_id' => $student->id]);

        // 不能派工、不能開始處理；別人的報修單看不到。
        $this->post(route('repairs.assign', $case), ['assigned_to' => $technician->id])->assertForbidden();
        $this->post(route('repairs.start', $case))->assertForbidden();
        $this->get(route('repairs.show', $others))->assertForbidden();
    }

    public function test_a_student_cannot_accept_even_their_own_repair_but_the_designated_acceptor_can(): void
    {
        $student = $this->makeUserWithRole('student', '學生');
        $executive = $this->makeUserWithRole('executive', '主管');
        $technician = $this->makeTechnician();
        $own = RepairRequestFactory::new()->create(['status' => 'pending_review', 'requester_id' => $student->id, 'assigned_to' => $technician->id, 'accepted_by' => $executive->id]);

        // 學生不能驗收（連自己報修的也不行），也不能退回。
        $this->actingAs($student)->post(route('repairs.complete', $own))->assertForbidden();
        $this->post(route('repairs.reject', $own), ['rejection_reason' => '還是壞的'])->assertForbidden();
        $this->assertSame('pending_review', $own->fresh()->status->value);

        // 指定的驗收人可以驗收。
        $this->actingAs($executive)->post(route('repairs.complete', $own))->assertSessionHasNoErrors();
        $this->assertSame('completed', RepairRequest::find($own->id)->status->value);
    }
}
