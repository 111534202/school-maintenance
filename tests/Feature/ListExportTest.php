<?php

namespace Tests\Feature;

use App\Exports\TableExport;
use App\Models\ClassroomReservation;
use App\Models\MaintenanceOrder;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\Concerns\CreatesReservations;
use Tests\TestCase;

/**
 * 報修單、保養工單、空間申請的列表匯出 Excel：
 * 匯出的範圍＝目前搜尋與篩選條件下的全部資料（不分頁），而且和畫面一樣受權限限制（只含自己看得到的）。
 */
class ListExportTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;
    use CreatesReservations;

    /** 取出匯出檔的資料列（第一欄是編號）。 */
    private function exportedIds(string $prefix): array
    {
        $rows = [];
        Excel::matchByRegex();   // 檔名帶時間，用規則比對
        Excel::assertDownloaded(
            '/^' . preg_quote($prefix, '/') . '.*\.xlsx$/u',
            function (TableExport $export) use (&$rows) {
                $rows = $export->array();

                return true;
            }
        );

        return collect($rows)->pluck(0)->sort()->values()->all();
    }

    public function test_repair_export_follows_filters_and_permissions(): void
    {
        Excel::fake();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $mine = RepairRequest::factory()->create(['title' => '我的投影機', 'status' => 'pending', 'requester_id' => $teacher->id]);
        $others = RepairRequest::factory()->create(['title' => '別人的冷氣', 'status' => 'completed', 'requester_id' => $this->makeUserWithRole('teacher', '教師')->id]);

        // 老師只能匯出自己看得到的案件。
        $this->actingAs($teacher)->get(route('repairs.export'))->assertOk();
        $this->assertSame([$mine->id], $this->exportedIds('報修單-'));

        // 能派工的人匯出全部；也會套用目前的搜尋條件。
        Excel::fake();
        $this->actingAs($this->makeUserWithPermissions(['repairs.dispatch']))->get(route('repairs.export'))->assertOk();
        $this->assertSame([$mine->id, $others->id], $this->exportedIds('報修單-'));

        Excel::fake();
        $this->get(route('repairs.export', ['keyword' => '冷氣']))->assertOk();
        $this->assertSame([$others->id], $this->exportedIds('報修單-'));

        Excel::fake();
        $this->get(route('repairs.export', ['status' => 'pending']))->assertOk();
        $this->assertSame([$mine->id], $this->exportedIds('報修單-'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'downloaded']);   // 匯出有留紀錄
    }

    public function test_repair_export_has_readable_chinese_headings(): void
    {
        Excel::fake();
        RepairRequest::factory()->create(['impact_level' => 'high', 'is_class_affected' => true]);
        $this->actingAs($this->makeUserWithPermissions(['repairs.dispatch']))->get(route('repairs.export'));

        Excel::matchByRegex();
        Excel::assertDownloaded('/^報修單-.*\.xlsx$/u', function (TableExport $export) {
            $this->assertSame('編號', $export->headings()[0]);
            $this->assertContains('報修人', $export->headings());
            $this->assertSame(count($export->headings()), count($export->array()[0]));
            $this->assertSame('嚴重', $export->array()[0][3]);   // 影響程度寫中文，不是 high
            $this->assertSame('是', $export->array()[0][4]);

            return true;
        });
    }

    public function test_maintenance_order_export_needs_view_permission_and_follows_filters(): void
    {
        Excel::fake();
        $late = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->subDays(5)]);
        $future = MaintenanceOrder::factory()->create(['status' => 'pending', 'scheduled_date' => now()->addDays(30)]);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('maintenance-orders.export'))->assertForbidden();

        $this->actingAs($this->makeUserWithPermissions(['maintenance.view']))->get(route('maintenance-orders.export'))->assertOk();
        $this->assertSame([$late->id, $future->id], $this->exportedIds('保養工單-'));

        Excel::fake();
        $this->get(route('maintenance-orders.export', ['due' => 'overdue']))->assertOk();
        $this->assertSame([$late->id], $this->exportedIds('保養工單-'));
    }

    public function test_reservation_export_gives_managers_everything_and_others_only_their_own(): void
    {
        Excel::fake();
        $classroom = \App\Models\Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'X101', 'name' => 'X101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $other = $this->makeUserWithRole('teacher', '教師');
        $make = fn ($user, $class, $status) => $this->makeReservation(['classroom_id' => $classroom->id, 'requester_id' => $user->id, 'class_name' => $class, 'reservation_date' => now()->addDays(3)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => $status]);
        $mine = $make($teacher, '我的班', 'pending');
        $theirs = $make($other, '別人的班', 'approved');

        $this->actingAs($teacher)->get(route('reservations.export'))->assertOk();
        $this->assertSame([$mine->id], $this->exportedIds('空間申請-'));

        Excel::fake();
        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'))->get(route('reservations.export'))->assertOk();
        $this->assertSame([$mine->id, $theirs->id], $this->exportedIds('空間申請-'));

        Excel::fake();
        $this->get(route('reservations.export', ['status' => 'pending']))->assertOk();
        $this->assertSame([$mine->id], $this->exportedIds('空間申請-'));

        Excel::fake();
        $this->get(route('reservations.export', ['keyword' => '別人']))->assertOk();
        $this->assertSame([$theirs->id], $this->exportedIds('空間申請-'));
    }

    public function test_the_list_pages_show_an_export_button_with_the_current_filters(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['repairs.dispatch', 'maintenance.view', 'reservations.manage']));

        $this->get(route('repairs.index', ['keyword' => 'abc']))->assertSee(route('repairs.export', ['keyword' => 'abc']), false)->assertSee('匯出 Excel');
        $this->get(route('maintenance-orders.index'))->assertSee('匯出 Excel');
        $this->get(route('reservations.index', ['status' => 'pending']))->assertSee(route('reservations.export', ['status' => 'pending']), false);
    }
}
