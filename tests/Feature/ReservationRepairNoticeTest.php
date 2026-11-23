<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Services\ClassroomRepairNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\Concerns\CreatesReservations;
use Tests\TestCase;

/**
 * 空間預約結合設備報修：
 * - 教室有設備在等待維修（報修未結案）時，選好教室後顯示「印表機正在等待維修」。
 * - 只是提醒（黃色），仍可預約、仍可核准（依《報修與保養流程修改說明》，核心設備與暫停預約的規則已拿掉）。
 * - 已結案、已退回的報修不算。
 */
class ReservationRepairNoticeTest extends TestCase
{
    use CreatesReservations;
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->classroom = $this->makeClassroom('A101');
    }

    private function makeClassroom(string $code, array $attrs = []): Classroom
    {
        return Classroom::create($attrs + [
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => $code, 'name' => $code . ' 教室',
            'is_active' => true,
        ]);
    }

    private function makeDevice(Classroom $classroom, string $code, string $category): Device
    {
        return Device::create([
            'device_code' => $code,
            'device_category_id' => DeviceCategory::firstOrCreate(['name' => $category])->id,
            'classroom_id' => $classroom->id,
            'status' => 'normal',
        ]);
    }

    private function payload(Classroom $classroom): array
    {
        return [
            'classroom_id' => $classroom->id,
            'class_name' => '一年甲班',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => '班級活動',
        ];
    }

    public function test_every_open_repair_is_shown_as_waiting_for_repair(): void
    {
        $printer = $this->makeDevice($this->classroom, 'DEV-0003', '印表機');
        foreach (['pending', 'assigned', 'pending_review'] as $status) {
            RepairRequest::query()->delete();
            RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => $status]);

            $notices = ClassroomRepairNotices::for([$this->classroom->id]);
            $this->assertSame([['text' => '印表機正在等待維修']], $notices[$this->classroom->id], $status);
        }
    }

    public function test_completed_repairs_and_other_classrooms_are_not_listed(): void
    {
        $other = $this->makeClassroom('B201');
        $printer = $this->makeDevice($this->classroom, 'DEV-0003', '印表機');
        $otherDevice = $this->makeDevice($other, 'DEV-0009', '電腦');
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'completed']);
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'returned']);   // 已退回也不算
        RepairRequest::factory()->create(['device_id' => $otherDevice->id, 'status' => 'pending']);

        $this->assertArrayNotHasKey($this->classroom->id, ClassroomRepairNotices::for([$this->classroom->id]));
    }

    public function test_repair_without_device_but_with_classroom_is_listed(): void
    {
        RepairRequest::factory()->create(['device_id' => null, 'device_note' => null, 'classroom_id' => $this->classroom->id, 'title' => '冷氣不冷', 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id]);

        $this->assertSame('報修案件「冷氣不冷」正在等待維修', $notices[$this->classroom->id][0]['text']);
    }

    // 沒有設備、有填「設備位置／描述」：顯示描述前 10 個字，超過的用「…」，完整內容放在 full（點了才顯示）；免登入的課表不顯示描述。
    public function test_repair_without_device_shows_the_first_ten_characters_of_the_note(): void
    {
        RepairRequest::factory()->create(['device_id' => null, 'device_note' => '講台旁邊的延長線插座沒有電', 'classroom_id' => $this->classroom->id, 'status' => 'pending']);
        RepairRequest::factory()->create(['device_id' => null, 'device_note' => '後門門鎖', 'classroom_id' => $this->classroom->id, 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id])[$this->classroom->id];
        $this->assertSame('「講台旁邊的延長線插座…」正在等待維修', $notices[0]['text']);
        $this->assertSame('「講台旁邊的延長線插座沒有電」正在等待維修', $notices[0]['full']);
        $this->assertSame(['text' => '「後門門鎖」正在等待維修'], $notices[1]);

        $guest = ClassroomRepairNotices::for([$this->classroom->id], withTitles: false, withNotes: false)[$this->classroom->id];
        $this->assertSame([['text' => '有報修案件正在等待維修']], $guest);
    }

    // 發現地點和設備所在的教室不同：提醒算在發現地點那間，設備所在的教室不提醒（一張報修只算一間教室）。
    public function test_a_repair_counts_in_its_reported_location_only(): void
    {
        $other = \App\Models\Classroom::create(['classroom_code' => 'OTHER-1', 'campus' => '本', 'building' => 'O', 'floor' => '1', 'name' => '別的教室', 'is_active' => true]);
        $projector = $this->makeDevice($this->classroom, 'DEV-LOC', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'classroom_id' => $other->id, 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id, $other->id]);
        $this->assertArrayNotHasKey($this->classroom->id, $notices);
        $this->assertSame('投影機正在等待維修', $notices[$other->id][0]['text']);
    }

    public function test_dropdown_has_no_hint_and_a_repair_still_allows_booking(): void
    {
        $printer = $this->makeDevice($this->classroom, 'DEV-0003', '印表機');
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'pending']);

        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('選擇教室')
            ->assertSee('data-id="' . $this->classroom->id . '"', false)
            ->assertDontSee('暫停預約</span>', false)   // 選單裡不放提示
            // 提醒資料交給選好教室後的提示框使用（@json 會把中文轉成 \uXXXX，所以檢查傳給畫面的資料）
            ->assertViewHas('repairNotices', fn ($notices) => $notices[$this->classroom->id][0]['text'] === '印表機正在等待維修');

        $this->post(route('reservations.store'), $this->payload($this->classroom))->assertSessionHas('success');
    }

    // 報修已派工、待驗收（設備顯示待維修）時，一樣只是提醒：可以查課表、可以送出申請。
    public function test_a_device_awaiting_repair_never_suspends_booking(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0100', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'pending_review']);

        $this->get(route('reservations.create'))->assertOk()->assertSee('data-id="' . $this->classroom->id . '"', false)->assertDontSee('暫停預約');
        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $this->classroom->id,
            'date' => now()->addDays(3)->toDateString(),
        ]))->assertOk();
        $this->post(route('reservations.store'), $this->payload($this->classroom))->assertSessionHas('success');
        $this->assertDatabaseCount('classroom_reservations', 1);
    }

    // 審核時看得到提醒，仍可以核准。
    public function test_pending_request_can_be_approved_while_a_device_is_being_repaired(): void
    {
        $reservation = $this->makeReservation($this->payload($this->classroom) + ['requester_id' => auth()->id(), 'status' => 'pending']);
        $projector = $this->makeDevice($this->classroom, 'DEV-0100', '投影機');
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'assigned']);

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('教室設備報修中')
            ->assertSee('投影機正在等待維修')
            ->assertDontSee('暫停預約');

        $this->post(route('reservations.approve', $reservation))->assertSessionHas('success');
        $this->assertSame('approved', $reservation->fresh()->status);
    }

    public function test_classroom_options_are_coloured_by_repair_status(): void
    {
        $repairing = $this->makeClassroom('B201');
        $printer = $this->makeDevice($repairing, 'DEV-0003', '印表機');
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'pending']);

        $html = $this->get(route('reservations.create'))->assertOk()->getContent();

        // 黃＝有設備在報修、黑＝正常（沒有額外顏色）；不再有紅色的暫停預約。
        $this->assertStringNotContainsString('data-tone="text-danger', $html);
        $this->assertMatchesRegularExpression('/room-has-repair fw-semibold"\s+data-tone="room-has-repair fw-semibold"\s+role="option"\s+data-id="' . $repairing->id . '"/', $html);
        $this->assertMatchesRegularExpression('/data-tone=""\s+role="option"\s+data-id="' . $this->classroom->id . '"/', $html);
    }
}
