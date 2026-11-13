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
 * 新增報修：設備條碼（device_id）、設備位置／描述（device_note）、設備地點（classroom_id）三項至少要有一項。
 * 只填標題與故障描述不能送出，否則維修人員不知道是哪裡的哪個設備。
 */
class RepairLocationRequiredTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function base(array $extra = []): array
    {
        return array_merge([
            'title' => '投影機沒畫面', 'description' => '開機後沒有畫面。',
            'impact_level' => 'low', 'is_class_affected' => '0',
        ], $extra);
    }

    private function classroom(): Classroom
    {
        return Classroom::create([
            'campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室',
            'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal',
        ]);
    }

    public function test_title_and_description_alone_cannot_be_submitted(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->base())->assertSessionHasErrors('device_note');

        $this->assertSame(0, RepairRequest::count());
    }

    public function test_blank_spaces_in_the_device_note_do_not_count(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->base(['device_note' => '   ']))->assertSessionHasErrors('device_note');

        $this->assertSame(0, RepairRequest::count());
    }

    public function test_the_error_message_is_shown_next_to_the_form_field(): void
    {
        $this->loginAsAnyUser();

        $this->from(route('repairs.create'))->post(route('repairs.store'), $this->base());

        $this->get(route('repairs.create'))->assertSee('請至少提供一項', false);
    }

    public function test_a_device_note_alone_is_enough(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->base(['device_note' => '三樓走廊的投影機']))->assertSessionHasNoErrors();

        $this->assertSame(1, RepairRequest::count());
    }

    public function test_a_classroom_alone_is_enough(): void
    {
        $this->loginAsAnyUser();
        $classroom = $this->classroom();

        $this->post(route('repairs.store'), $this->base(['classroom_id' => $classroom->id]))->assertSessionHasNoErrors();

        $this->assertSame($classroom->id, RepairRequest::firstOrFail()->classroom_id);
    }

    public function test_a_scanned_device_alone_is_enough(): void
    {
        $this->loginAsAnyUser();
        $classroom = $this->classroom();
        $category = DeviceCategory::create(['name' => '投影機', 'category_code' => 'PRJ']);
        $device = Device::create([
            'device_code' => 'PRJ-0001', 'name' => '投影機', 'device_category_id' => $category->id,
            'classroom_id' => $classroom->id, 'status' => 'active',
        ]);

        $this->post(route('repairs.store'), $this->base(['device_id' => $device->id]))->assertSessionHasNoErrors();

        $this->assertSame($device->id, RepairRequest::firstOrFail()->device_id);
    }
}
