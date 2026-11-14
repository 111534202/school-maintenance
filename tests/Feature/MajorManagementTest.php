<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 科系主檔與空間申請的「科系 → 年級 → 班級」下拉選單：
 * - 主檔：新增／修改／停用／刪除、年級數與班數的範圍、權限。
 * - 空間申請：先選科系、再選年級、最後選班級，班級名稱由系統組成（例如資一忠）；
 *   各科系的年級數、班數不同；科系主檔還是空的時候仍可自己輸入班級名稱。
 */
class MajorManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function major(array $attrs = []): Major
    {
        return Major::create($attrs + ['name' => '資訊科', 'major_code' => 'CS', 'abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4, 'is_active' => true]);
    }

    private ?Classroom $classroomCache = null;

    private function classroom(): Classroom
    {
        return $this->classroomCache ??= Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室',
            'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    private function request(?Major $major = null, array $override = []): array
    {
        return array_merge([
            'classroom_id' => $this->classroom()->id,
            'major_id' => $major?->id, 'class_grade' => 1, 'class_section' => 1,
            'reservation_date' => now()->addDays(3)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => '班級活動',
        ], $override);
    }

    // ---------- 科系主檔 ----------

    public function test_only_users_with_the_majors_permission_can_open_the_master(): void
    {
        $this->actingAs($this->makeItManager());   // 設備管理員預設有科系主檔權限（PermissionCatalog 預設值）
        $this->get(route('majors.index'))->assertOk();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('majors.index'))->assertForbidden();
        $this->post(route('majors.store'), ['name' => 'X', 'abbreviation' => 'X', 'grade_count' => 5, 'class_count' => 2, 'is_active' => 1])->assertForbidden();
    }

    public function test_admin_can_create_edit_toggle_and_delete_a_major_and_it_is_logged(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('majors.store'), ['name' => '護理科', 'major_code' => 'NU', 'abbreviation' => '護', 'grade_count' => 5, 'class_count' => 3, 'is_active' => '1'])
            ->assertRedirect(route('majors.index'))->assertSessionHas('success');
        $major = Major::firstWhere('name', '護理科');
        $this->assertSame('護三孝', $major->classLabel(3, 2));
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $major->id]);

        $this->put(route('majors.update', $major), ['name' => '護理科', 'major_code' => 'NU', 'abbreviation' => '護', 'grade_count' => 4, 'class_count' => 2, 'is_active' => '1'])->assertSessionHas('success');
        $this->assertSame(4, $major->fresh()->grade_count);

        $this->patch(route('majors.toggle', $major))->assertSessionHas('success');
        $this->assertFalse($major->fresh()->is_active);

        $this->delete(route('majors.destroy', $major))->assertRedirect(route('majors.index'));
        $this->assertDatabaseMissing('majors', ['id' => $major->id]);
    }

    // 年級數 1～9、班數 1～4（班名只有忠孝仁愛）；名稱不能重複。
    public function test_grade_and_class_counts_are_limited_and_names_must_be_unique(): void
    {
        $this->loginAsAnyUser();
        $this->major();

        $base = ['name' => '電機科', 'abbreviation' => '電', 'grade_count' => 5, 'class_count' => 2, 'is_active' => '1'];
        $this->post(route('majors.store'), ['grade_count' => 0] + $base)->assertSessionHasErrors('grade_count');
        $this->post(route('majors.store'), ['grade_count' => 10] + $base)->assertSessionHasErrors('grade_count');
        $this->post(route('majors.store'), ['class_count' => 5] + $base)->assertSessionHasErrors('class_count');
        $this->post(route('majors.store'), ['class_count' => 0] + $base)->assertSessionHasErrors('class_count');
        $this->post(route('majors.store'), ['name' => '資訊科'] + $base)->assertSessionHasErrors('name');
        $this->post(route('majors.store'), ['abbreviation' => ''] + $base)->assertSessionHasErrors('abbreviation');
        $this->assertSame(1, Major::count());

        $this->post(route('majors.store'), $base)->assertSessionHasNoErrors();   // 5 年級、2 班可以
        $this->post(route('majors.store'), ['name' => '大學部', 'abbreviation' => '大', 'grade_count' => 4, 'class_count' => 4, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(3, Major::count());
    }

    public function test_class_label_uses_abbreviation_chinese_grade_and_section_name(): void
    {
        $major = $this->major(['abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4]);

        $this->assertSame('資一忠', $major->classLabel(1, 1));
        $this->assertSame('資五愛', $major->classLabel(5, 4));
        $this->assertSame('資三仁', $major->classLabel(3, 3));
        $this->assertTrue($major->hasClass(5, 4));
        $this->assertFalse($major->hasClass(6, 1));   // 只有 5 個年級
        $this->assertFalse($major->hasClass(1, 5));   // 只有 4 班
    }

    // 已經有空間申請選過的科系不能刪（改用停用）；沒人用的才可以刪。
    public function test_a_major_used_by_a_reservation_cannot_be_deleted(): void
    {
        $this->loginAsAnyUser();
        $major = $this->major();
        ClassroomReservation::create([
            'classroom_id' => $this->classroom()->id, 'requester_id' => User::factory()->create()->id,
            'class_name' => '資一忠', 'major_id' => $major->id, 'class_grade' => 1, 'class_section' => 1, 'status' => 'pending',
        ]);

        $this->delete(route('majors.destroy', $major))->assertSessionHas('error');
        $this->assertDatabaseHas('majors', ['id' => $major->id]);
    }

    // ---------- 空間申請的下拉選單 ----------

    public function test_the_reservation_page_shows_the_major_dropdown_only_with_active_majors(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->classroom();   // 沒有任何教室時頁面不會畫出申請表單

        // 科系主檔是空的：退回自己輸入班級名稱。
        $this->get(route('reservations.create'))->assertOk()->assertDontSee('id="major_id"', false)->assertSee('id="class_name"', false);

        $this->major();
        $this->major(['name' => '舊科系', 'major_code' => 'OLD', 'abbreviation' => '舊', 'is_active' => false]);
        $this->get(route('reservations.create'))->assertOk()
            ->assertSee('id="major_id"', false)->assertSee('資訊科')->assertDontSee('舊科系');   // 停用的科系不出現
    }

    public function test_choosing_major_grade_and_section_builds_the_class_name(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $major = $this->major(['abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4]);
        $this->actingAs($teacher);

        $this->post(route('reservations.store'), $this->request($major, ['class_grade' => 3, 'class_section' => 2, 'class_name' => '亂填的文字']))->assertSessionHasNoErrors();

        $reservation = ClassroomReservation::firstOrFail();
        $this->assertSame('資三孝', $reservation->class_name);   // 以系統組的為準，不信任前端送來的文字
        $this->assertSame($major->id, $reservation->major_id);
        $this->assertSame(3, $reservation->class_grade);
        $this->assertSame(2, $reservation->class_section);
    }

    // 各科系的年級數與班數不同：只有 4 年級 2 班的科系，不能選第 5 年級或第 3 班。
    public function test_grade_and_section_must_exist_in_the_chosen_major(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $small = $this->major(['name' => '大學部', 'major_code' => 'UG', 'abbreviation' => '大', 'grade_count' => 4, 'class_count' => 2]);

        $this->post(route('reservations.store'), $this->request($small, ['class_grade' => 5]))->assertSessionHasErrors('class_section');
        $this->post(route('reservations.store'), $this->request($small, ['class_section' => 3]))->assertSessionHasErrors('class_section');
        $this->post(route('reservations.store'), $this->request($small, ['class_grade' => 4, 'class_section' => 2]))->assertSessionHasNoErrors();
        $this->assertSame('大四孝', ClassroomReservation::firstOrFail()->class_name);
    }

    public function test_year_or_class_cannot_be_left_out_and_an_inactive_major_is_refused(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $major = $this->major();
        $off = $this->major(['name' => '停用科', 'major_code' => 'OFF', 'abbreviation' => '停', 'is_active' => false]);

        $this->post(route('reservations.store'), $this->request($major, ['class_grade' => null]))->assertSessionHasErrors('class_grade');
        $this->post(route('reservations.store'), $this->request($major, ['class_section' => null]))->assertSessionHasErrors('class_section');
        $this->post(route('reservations.store'), $this->request($off))->assertSessionHasErrors('major_id');
        $this->assertSame(0, ClassroomReservation::count());
    }

    // 科系主檔已經有啟用中的科系時，不能繞過下拉選單直接自己輸入班級名稱。
    public function test_free_text_class_names_are_refused_once_the_major_master_has_active_majors(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $classroom = $this->classroom();
        $payload = ['classroom_id' => $classroom->id, 'class_name' => '自己亂打班', 'reservation_date' => now()->addDays(3)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00'];

        // 科系主檔還是空的：舊做法照常可用。
        $this->post(route('reservations.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('自己亂打班', ClassroomReservation::firstOrFail()->class_name);

        $this->major();
        $this->post(route('reservations.store'), array_merge($payload, ['start_time' => '11:00', 'end_time' => '12:00']))->assertSessionHasErrors('major_id');
        $this->assertSame(1, ClassroomReservation::count());
    }

    // 科系被停用或刪除之後，舊申請上的班級名稱不受影響。
    public function test_old_requests_keep_their_class_name_when_the_major_is_removed(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $major = $this->major();
        $this->actingAs($teacher);
        $this->post(route('reservations.store'), $this->request($major))->assertSessionHasNoErrors();

        $major->update(['is_active' => false]);
        $major->delete();   // 還有申請選過時，畫面會擋下；這裡直接刪資料庫列，確認外鍵不會連帶刪掉申請

        $reservation = ClassroomReservation::firstOrFail();
        $this->assertSame('資一忠', $reservation->class_name);
        $this->assertNull($reservation->major_id);
    }
}
