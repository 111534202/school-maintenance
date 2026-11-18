<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 新增／修改設備時即時檢查設備編號（devices.check-code），跟零件編號的檢查同一種做法：
 * 重複的編號回 available=false 並說明是哪一台；修改時排除自己；沒有設備主檔權限的人不能用。
 */
class DeviceCodeCheckTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_duplicate_code_is_refused_and_own_code_is_allowed_when_editing(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['devices.manage']));
        $device = $this->makeDevice('DEV-0100');

        $this->getJson(route('devices.check-code', ['code' => 'DEV-0100']))
            ->assertOk()
            ->assertJson(['available' => false])
            ->assertJsonFragment(['message' => '「DEV-0100」已經是設備「測試教室 測試電腦」的設備編號，請換一個。']);
        $this->getJson(route('devices.check-code', ['code' => 'DEV-0100', 'ignore' => $device->id]))
            ->assertJson(['available' => true]);
        $this->getJson(route('devices.check-code', ['code' => 'DEV-NEW']))
            ->assertJson(['available' => true]);
        $this->getJson(route('devices.check-code', ['code' => '']))
            ->assertJson(['available' => false]);
    }

    public function test_code_of_a_disabled_device_still_counts_as_used(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['devices.manage']));
        $this->makeDevice('DEV-0200')->delete();

        $this->getJson(route('devices.check-code', ['code' => 'DEV-0200']))
            ->assertJson(['available' => false]);
    }

    /** 跟零件編號同一套規則：格式限英文字母、數字、- _ .，最多 20 個字；也不能跟零件編號重複（大小寫視為相同）。 */
    public function test_format_and_part_code_conflicts_are_refused_on_check_and_save(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['devices.manage']));
        \App\Models\Part::create(['part_code' => 'HDMI-2', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1, 'current_stock' => 0]);

        $this->getJson(route('devices.check-code', ['code' => 'hdmi-2']))
            ->assertJson(['available' => false])
            ->assertJsonFragment(['message' => '「hdmi-2」已經是零件「HDMI 線」的零件編號，請換一個。']);
        $this->getJson(route('devices.check-code', ['code' => '1111喔']))
            ->assertJson(['available' => false])
            ->assertJsonFragment(['message' => '設備編號只能用英文字母、數字、「-」、「_」、「.」，最多 20 個字。']);
        $this->getJson(route('devices.check-code', ['code' => str_repeat('A', 21)]))->assertJson(['available' => false]);

        $device = $this->makeDevice('DEV-0300');
        $payload = [
            'device_category_id' => $device->device_category_id, 'classroom_id' => $device->classroom_id, 'status' => 'normal',
        ];
        $this->post(route('devices.store'), $payload + ['device_code' => 'HDMI-2'])
            ->assertSessionHasErrors(['device_code' => '「HDMI-2」已經是零件「HDMI 線」的零件編號，請換一個。']);
        $this->post(route('devices.store'), $payload + ['device_code' => 'A 101'])
            ->assertSessionHasErrors(['device_code' => '設備編號只能用英文字母、數字、「-」、「_」、「.」，最多 20 個字。']);
        $this->post(route('devices.store'), $payload + ['device_code' => 'dev-0300'])
            ->assertSessionHasErrors('device_code');
    }

    public function test_users_without_device_permission_cannot_use_the_check(): void
    {
        $this->actingAs($this->makeUserWithPermissions([]));

        $this->getJson(route('devices.check-code', ['code' => 'DEV-0100']))->assertForbidden();
    }
}
