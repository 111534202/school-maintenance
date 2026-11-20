<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use App\Support\ReservationExportColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 空間申請匯出：先預覽、勾選要匯出的欄位與申請，再匯出（和零件庫存的匯出方式一致）。
 */
class ReservationExportPreviewTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function reservation(User $user, string $class): ClassroomReservation
    {
        $classroom = Classroom::firstOrCreate(['classroom_code' => 'A101'], ['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);

        return ClassroomReservation::create(['classroom_id' => $classroom->id, 'requester_id' => $user->id, 'class_name' => $class, 'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '08:10', 'end_time' => '09:00', 'purpose' => '班級活動', 'status' => 'pending']);
    }

    /** @return list<list<mixed>> 下載到的 xlsx 內容（含標題列） */
    private function downloaded(string $url): array
    {
        $response = $this->get($url)->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();

        return Excel::toArray(new class implements ToArray
        {
            public function array(array $rows): void {}
        }, $path, null, \Maatwebsite\Excel\Excel::XLSX)[0];
    }

    public function test_the_list_page_has_a_preview_dialog_with_columns_and_rows_to_tick(): void
    {
        $manager = $this->makeItManager();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $mine = $this->reservation($teacher, '甲班專用活動');
        $this->actingAs($manager)->get(route('reservations.index'))->assertOk()
            ->assertSee('id="reservationExportModal"', false)
            ->assertSee('name="columns[]" value="class_name"', false)
            ->assertSee('name="ids[]" value="' . $mine->id . '"', false)
            ->assertSee('甲班專用活動');
    }

    public function test_the_export_only_contains_the_ticked_columns_and_rows(): void
    {
        $manager = $this->makeItManager();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $a = $this->reservation($teacher, '甲班活動');
        $b = $this->reservation($teacher, '乙班活動');
        $this->actingAs($manager);

        $rows = $this->downloaded(route('reservations.export', ['columns' => ['class_name', 'status'], 'ids' => [$b->id]]));

        $this->assertSame(['申請班級', '申請狀態'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('乙班活動', $rows[1][0]);
    }

    public function test_without_choices_everything_is_exported_and_other_peoples_ids_cannot_be_forced_in(): void
    {
        $teacherA = $this->makeUserWithRole('teacher', '教師');
        $teacherB = User::factory()->create(['role_id' => $teacherA->role_id]);
        $this->reservation($teacherA, '甲班活動');
        $others = $this->reservation($teacherB, '乙班活動');

        $this->actingAs($teacherA);
        $all = $this->downloaded(route('reservations.export'));
        $this->assertSame(array_values(ReservationExportColumns::COLUMNS), $all[0]);
        $this->assertCount(2, $all);   // 標題列 + 只有自己的那一筆

        // 硬塞別人的申請編號也沒用：範圍仍然限於自己的申請。
        $forced = $this->downloaded(route('reservations.export', ['ids' => [$others->id]]));
        $this->assertCount(1, $forced);
    }

    public function test_people_without_review_permission_get_the_dialog_on_the_apply_page_with_only_their_own_requests(): void
    {
        $teacherA = $this->makeUserWithRole('teacher', '教師');
        $teacherB = User::factory()->create(['role_id' => $teacherA->role_id]);
        $this->reservation($teacherA, '甲班專用活動');
        $this->reservation($teacherB, '乙班專用活動');

        $this->actingAs($teacherA)->get(route('reservations.index'))->assertOk()
            ->assertSee('id="reservationExportModal"', false)
            ->assertDontSee('乙班專用活動');
    }
}
