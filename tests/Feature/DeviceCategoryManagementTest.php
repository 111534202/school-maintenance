<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 設備類別主檔：權限、新增／修改／刪除、名稱不可重複、還有設備使用時不能刪除、搜尋。 */
class DeviceCategoryManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
    }

    // 只有被勾選「設備類別」權限的人進得去：維修人員 403，資訊組主管（預設有勾）可以。
    public function test_only_users_with_device_categories_manage_permission_can_open_it(): void
    {
        $this->actingAs($this->makeTechnician());
        $this->get(route('device-categories.index'))->assertForbidden();
        $this->post(route('device-categories.store'), ['name' => '駭客類別'])->assertForbidden();

        $this->actingAs($this->makeItManager());
        $this->get(route('device-categories.index'))->assertOk();
    }

    public function test_admin_can_create_a_category_and_it_is_logged(): void
    {
        $this->post(route('device-categories.store'), ['name' => '投影機'])
            ->assertRedirect(route('device-categories.index'))->assertSessionHas('success');

        $category = DeviceCategory::firstWhere('name', '投影機');
        $this->assertNotNull($category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_id' => $category->id, 'loggable_type' => DeviceCategory::class]);
    }

    public function test_create_requires_a_unique_name(): void
    {
        DeviceCategory::create(['name' => '投影機']);

        $this->post(route('device-categories.store'), ['name' => '投影機'])->assertSessionHasErrors('name');
        $this->post(route('device-categories.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(1, DeviceCategory::count());
    }

    // 修改時，不改名稱不會被誤判重複；不能改成別的類別已經用的名稱。
    public function test_update_keeps_own_name_but_cannot_take_another_name(): void
    {
        $projector = DeviceCategory::create(['name' => '投影機']);
        DeviceCategory::create(['name' => '交換器']);

        $this->put(route('device-categories.update', $projector), ['name' => '投影機'])->assertSessionHasNoErrors();
        $this->put(route('device-categories.update', $projector), ['name' => '交換器'])->assertSessionHasErrors('name');
        $this->put(route('device-categories.update', $projector), ['name' => '短焦投影機'])->assertRedirect(route('device-categories.index'));

        $this->assertSame('短焦投影機', $projector->fresh()->name);
    }

    // 還有設備使用的類別不能刪除（保持原樣並顯示錯誤）；沒有設備用的可以刪。
    public function test_category_in_use_cannot_be_deleted_but_unused_one_can(): void
    {
        $used = DeviceCategory::create(['name' => '使用中類別']);
        $unused = DeviceCategory::create(['name' => '沒人用的類別']);
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'room_code' => 'A1', 'room_name' => 'A1']);
        Device::create(['device_code' => 'D-1', 'device_category_id' => $used->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);

        $this->delete(route('device-categories.destroy', $used))->assertSessionHas('error');
        $this->assertNotNull($used->fresh());

        $this->delete(route('device-categories.destroy', $unused))->assertSessionHas('success');
        $this->assertDatabaseMissing('device_categories', ['id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'loggable_id' => $unused->id, 'loggable_type' => DeviceCategory::class]);
    }

    public function test_index_shows_device_counts_and_can_search(): void
    {
        $projector = DeviceCategory::create(['name' => '投影機']);
        DeviceCategory::create(['name' => '交換器']);
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'room_code' => 'A1', 'room_name' => 'A1']);
        Device::create(['device_code' => 'D-1', 'device_category_id' => $projector->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);
        Device::create(['device_code' => 'D-2', 'device_category_id' => $projector->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);

        $this->get(route('device-categories.index'))->assertOk()->assertSee('投影機')->assertSee('交換器');
        $this->get(route('device-categories.index', ['keyword' => '投影']))->assertSee('投影機')->assertDontSee('交換器');
    }
}
