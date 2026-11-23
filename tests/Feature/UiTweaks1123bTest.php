<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 11/23 劉家芸提出：教室主檔紅底看進行中工單、點列先開教室詳細；設備詳細顯示進行中工單、QR 下載小視窗；
 * 報修詳細的重複報修提示；建議處理時間不能早於現在。
 */
class UiTweaks1123bTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function setUpRoom(): array
    {
        $room = Classroom::create(['classroom_code' => 'UI-B1', 'campus' => '本', 'building' => 'U', 'floor' => '1', 'name' => '詳細測試教室', 'type' => '電腦教室', 'is_active' => true]);
        $device = Device::create(['device_code' => 'UIB-001', 'device_category_id' => DeviceCategory::create(['name' => '投影機'])->id, 'classroom_id' => $room->id, 'status' => 'normal']);

        return [$room, $device];
    }

    public function test_classroom_rows_are_red_by_open_repairs_and_open_the_detail(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        [$room] = $this->setUpRoom();
        // 沒綁設備、只填發現地點：沒有異常設備，但有進行中工單 → 紅底
        RepairRequest::factory()->create(['device_id' => null, 'classroom_id' => $room->id, 'status' => 'pending']);

        $html = $this->actingAs($admin)->get(route('classrooms.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<tr class="table-danger" data-modal-frame-href="' . route('classrooms.show', $room) . '"', $html);

        $this->actingAs($admin)->get(route('classrooms.show', $room))->assertOk()
            ->assertSeeInOrder(['詳細測試教室', '本 / U / 1', '電腦教室', '設備數', '1', '異常設備', '進行中工單', '1'])
            ->assertSee(route('classrooms.edit', $room), false);
    }

    public function test_device_detail_shows_open_repairs_and_a_qr_download_dialog(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        [, $device] = $this->setUpRoom();
        RepairRequest::factory()->count(2)->create(['device_id' => $device->id, 'status' => 'pending']);

        $this->actingAs($admin)->get(route('devices.show', $device))->assertOk()
            ->assertSeeInOrder(['進行中工單', '2'])
            ->assertSee('qrDownloadDialog', false)
            ->assertSee('下載成 JPG')
            ->assertSee('新分頁開啟')
            ->assertDontSee('預覽入口頁');
    }

    public function test_repair_detail_shows_the_repeat_repair_hint(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        [, $device] = $this->setUpRoom();
        $repairs = RepairRequest::factory()->count(3)->create(['device_id' => $device->id, 'status' => 'pending']);

        $this->actingAs($admin)->get(route('repairs.show', $repairs[0]))->assertOk()->assertSee('UIB-001 近30天報修3次');
    }

    public function test_the_suggested_time_cannot_be_in_the_past(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $tech = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)->post(route('repairs.assign', $repair), ['assigned_to' => $tech->id, 'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('scheduled_at');
        $this->actingAs($admin)->post(route('repairs.assign', $repair), ['assigned_to' => $tech->id, 'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasNoErrors();
    }
}
