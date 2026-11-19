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
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1']);
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
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1']);
        Device::create(['device_code' => 'D-1', 'device_category_id' => $projector->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);
        Device::create(['device_code' => 'D-2', 'device_category_id' => $projector->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);

        $this->get(route('device-categories.index'))->assertOk()->assertSee('投影機')->assertSee('交換器');
        $this->get(route('device-categories.index', ['keyword' => '投影']))->assertSee('投影機')->assertDontSee('交換器');
    }

    // 設備主檔右上角有「新增類別」（手機短字「+類別」），點開是設備類別的對話框。
    public function test_device_index_has_add_category_button_opening_the_dialog(): void
    {
        $html = $this->get(route('devices.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('device-categories.index') . '" data-modal-frame', $html);
        $this->assertStringContainsString('+類別', $html);
        $this->assertStringContainsString('+設備', $html);
    }

    // 對話框（?modal=1）：上面是新增類別的填寫框，下面是搜尋框與現有類別（名稱、使用中設備數、修改、刪除），不顯示頁首；
    // 還有使用中設備的類別，刪除鈕是停用的。
    public function test_dialog_shows_add_form_and_existing_list(): void
    {
        $used = DeviceCategory::create(['name' => '投影機']);
        DeviceCategory::create(['name' => '交換器']);
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1']);
        Device::create(['device_code' => 'D-1', 'device_category_id' => $used->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);

        $html = $this->get(route('device-categories.index', ['modal' => 1]))->assertOk()
            ->assertSee('新增類別')->assertSee('現有類別')->assertSee('使用中設備數')->assertSee('投影機')->assertSee('交換器')
            ->assertSee('name="keyword"', false)   // 現有類別上方的搜尋框（對話框裡也有）
            ->getContent();

        $this->assertStringContainsString('action="' . route('device-categories.store') . '"', $html);
        $this->assertSame(1, substr_count($html, 'type="submit" disabled'), '只有投影機（有使用中設備）的刪除鈕停用');
    }

    // 在對話框裡新增或刪除後留在對話框（網址保留 modal=1）；名稱重複會被擋下。
    public function test_add_and_delete_in_dialog_stay_in_dialog(): void
    {
        $this->post(route('device-categories.store'), ['name' => '冷氣', 'modal' => 1])
            ->assertRedirect(route('device-categories.index', ['modal' => 1]));
        $this->post(route('device-categories.store'), ['name' => '冷氣', 'modal' => 1])->assertSessionHasErrors('name');
        $this->assertSame(1, DeviceCategory::where('name', '冷氣')->count());

        $category = DeviceCategory::where('name', '冷氣')->first();
        $this->delete(route('device-categories.destroy', $category), ['modal' => 1])
            ->assertRedirect(route('device-categories.index', ['modal' => 1]));
        $this->assertDatabaseMissing('device_categories', ['id' => $category->id]);
    }

    // 只剩已停用設備（不算使用中）的類別也不能刪：資料還指向它，刪掉會違反外鍵。
    public function test_category_with_only_disabled_devices_cannot_be_deleted(): void
    {
        $category = DeviceCategory::create(['name' => '舊投影機']);
        $classroom = Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A1', 'name' => 'A1']);
        $device = Device::create(['device_code' => 'D-9', 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'disabled']);
        $device->delete();

        $this->delete(route('device-categories.destroy', $category))->assertSessionHas('error', __('device_categories.errors.has_disabled_devices'));
        $this->assertNotNull($category->fresh());
    }

    // 驗證錯誤的紅色提示框跟成功訊息一樣，右邊有 ✕ 可以關閉（共用版型）。
    public function test_validation_error_box_can_be_closed(): void
    {
        DeviceCategory::create(['name' => '投影機']);

        $this->from(route('device-categories.index', ['modal' => 1]))
            ->followingRedirects()
            ->post(route('device-categories.store'), ['name' => '投影機', 'modal' => 1])
            ->assertSee('這個類別名稱已經存在了')
            ->assertSee('alert alert-danger alert-dismissible fade show', false);
    }

    // 對話框裡搜尋類別名稱：結果留在對話框（網址帶著 modal=1）。
    public function test_dialog_search_filters_by_name(): void
    {
        DeviceCategory::create(['name' => '投影機']);
        DeviceCategory::create(['name' => '交換器']);

        $this->get(route('device-categories.index', ['modal' => 1, 'keyword' => '投影']))->assertOk()
            ->assertSee('投影機')->assertDontSee('交換器')
            ->assertSee('<input type="hidden" name="modal" value="1">', false);
    }
}
