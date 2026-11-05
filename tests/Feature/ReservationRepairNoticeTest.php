<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Services\ClassroomRepairNotices;
use App\Services\DeviceStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 空間預約結合設備報修：
 * - 教室有設備在等待維修（報修未結案）時，選好教室後顯示「印表機正在等待維修」。
 * - 只提醒（黃色）時仍可預約；核心設備的報修進入處理中、教室被標成設備異常時暫停預約（紅色）：
 *   可以查課表，但不能送出、不能核准。
 */
class ReservationRepairNoticeTest extends TestCase
{
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
            'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    private function makeDevice(Classroom $classroom, string $code, string $category, bool $core = false): Device
    {
        return Device::create([
            'device_code' => $code,
            'device_category_id' => DeviceCategory::firstOrCreate(['name' => $category])->id,
            'classroom_id' => $classroom->id,
            'status' => 'normal',
            'is_core' => $core,
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
        foreach (['pending', 'assigned', 'in_progress', 'pending_review'] as $status) {
            RepairRequest::query()->delete();
            RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => $status]);

            $notices = ClassroomRepairNotices::for([$this->classroom->id]);
            $this->assertSame([['text' => '印表機正在等待維修', 'core' => false]], $notices[$this->classroom->id], $status);
        }
    }

    public function test_completed_repairs_and_other_classrooms_are_not_listed(): void
    {
        $other = $this->makeClassroom('B201');
        $printer = $this->makeDevice($this->classroom, 'DEV-0003', '印表機');
        $otherDevice = $this->makeDevice($other, 'DEV-0009', '電腦');
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'completed']);
        RepairRequest::factory()->create(['device_id' => $otherDevice->id, 'status' => 'pending']);

        $this->assertArrayNotHasKey($this->classroom->id, ClassroomRepairNotices::for([$this->classroom->id]));
    }

    public function test_repair_without_device_but_with_classroom_is_listed(): void
    {
        RepairRequest::factory()->create(['device_id' => null, 'classroom_id' => $this->classroom->id, 'title' => '冷氣不冷', 'status' => 'pending']);

        $notices = ClassroomRepairNotices::for([$this->classroom->id]);

        $this->assertSame('報修案件「冷氣不冷」正在等待維修', $notices[$this->classroom->id][0]['text']);
    }

    public function test_dropdown_has_no_hint_and_non_core_repair_still_allows_booking(): void
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

        $this->assertSame([], ClassroomRepairNotices::suspendedIds([$this->classroom->id]));

        $this->post(route('reservations.store'), $this->payload($this->classroom))->assertSessionHas('success');
    }

    public function test_core_device_repair_only_suspends_booking_once_work_has_started(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0100', '投影機', core: true);
        $repair = RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'assigned']);

        // 報修還沒開始處理：只提醒，不暫停預約（沿用原本規則）。
        $this->assertSame([], ClassroomRepairNotices::suspendedIds([$this->classroom->id]));
        $this->assertSame([['text' => '投影機正在等待維修', 'core' => true]], ClassroomRepairNotices::for([$this->classroom->id])[$this->classroom->id]);
        $this->post(route('reservations.store'), $this->payload($this->classroom))->assertSessionHas('success');

        // 進入處理中 → 設備維修中 → 教室設備異常 → 暫停預約。
        DeviceStatusService::updateStatus($projector, 'repairing');
        $this->assertSame([$this->classroom->id], ClassroomRepairNotices::suspendedIds([$this->classroom->id]));

        // 仍可選、可查課表，但不能送出。
        $this->get(route('reservations.create'))->assertOk()->assertSee('data-id="' . $this->classroom->id . '"', false);
        $this->getJson(route('reservations.timetable.week', [
            'classroom_id' => $this->classroom->id,
            'date' => now()->addDays(3)->toDateString(),
        ]))->assertOk();
        $this->post(route('reservations.store'), $this->payload($this->classroom) + ['start_time' => '13:00', 'end_time' => '14:00'])
            ->assertSessionHas('error', '這間教室的核心設備正在維修，目前暫停預約，無法送出申請。');
        $this->assertDatabaseCount('classroom_reservations', 1);
    }

    public function test_abnormal_classroom_is_selectable_but_cannot_be_booked(): void
    {
        $projector = $this->makeDevice($this->classroom, 'DEV-0100', '投影機', core: true);
        DeviceStatusService::updateStatus($projector, 'repairing');

        $this->get(route('reservations.create'))->assertOk()->assertSee('data-id="' . $this->classroom->id . '"', false);
        $this->post(route('reservations.store'), $this->payload($this->classroom))->assertSessionHas('error');
    }

    public function test_pending_request_cannot_be_approved_while_a_core_device_is_being_repaired(): void
    {
        $reservation = ClassroomReservation::create($this->payload($this->classroom) + ['requester_id' => auth()->id(), 'status' => 'pending']);
        $projector = $this->makeDevice($this->classroom, 'DEV-0100', '投影機', core: true);
        RepairRequest::factory()->create(['device_id' => $projector->id, 'status' => 'in_progress']);
        DeviceStatusService::updateStatus($projector, 'repairing');   // 報修進入處理中時系統會這樣標記

        $this->actingAs($this->makeUserWithRole('it_manager', '資訊組主管'));

        $this->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('教室設備狀況')
            ->assertSee('投影機正在等待維修')
            ->assertSee('暫停預約');

        $this->post(route('reservations.approve', $reservation))->assertSessionHas('error');
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_classroom_options_are_coloured_by_repair_status(): void
    {
        $repairing = $this->makeClassroom('B201');
        $suspended = $this->makeClassroom('C301');
        $printer = $this->makeDevice($repairing, 'DEV-0003', '印表機');
        RepairRequest::factory()->create(['device_id' => $printer->id, 'status' => 'pending']);
        DeviceStatusService::updateStatus($this->makeDevice($suspended, 'DEV-0100', '投影機', core: true), 'repairing');

        $html = $this->get(route('reservations.create'))->assertOk()->getContent();

        // 紅＝暫停預約、黃＝有設備在報修、黑＝正常（沒有額外顏色）。
        $this->assertMatchesRegularExpression('/text-danger fw-semibold"\s+data-tone="text-danger fw-semibold"\s+role="option"\s+data-id="' . $suspended->id . '"/', $html);
        $this->assertMatchesRegularExpression('/room-has-repair fw-semibold"\s+data-tone="room-has-repair fw-semibold"\s+role="option"\s+data-id="' . $repairing->id . '"/', $html);
        $this->assertMatchesRegularExpression('/data-tone=""\s+role="option"\s+data-id="' . $this->classroom->id . '"/', $html);
    }
}
