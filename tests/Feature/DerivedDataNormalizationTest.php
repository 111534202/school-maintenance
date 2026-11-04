<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 第三正規化：保養計畫與保養工單針對「一台設備」或「一類設備」二選一；
 * 指定了設備，類別就由設備推得，不再另存 device_category_id（儲存時自動清掉），畫面用 categoryName() 顯示。
 */
class DerivedDataNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $categoryName = '投影機'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => $categoryName]);
        $room = Classroom::create(['campus' => 'A', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'R' . uniqid(), 'name' => 'x']);

        return Device::create(['device_code' => 'D' . uniqid(), 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
    }

    // 有設備的計畫：類別欄位自動清空，顯示的類別名稱來自設備。
    public function test_a_plan_bound_to_a_device_does_not_store_the_category_twice(): void
    {
        $device = $this->device();
        $plan = MaintenancePlan::create(['name' => '計畫', 'device_id' => $device->id, 'device_category_id' => $device->device_category_id, 'cycle_days' => 30, 'start_date' => '2026-10-01']);

        $this->assertNull($plan->fresh()->device_category_id);
        $this->assertSame('投影機', $plan->fresh()->categoryName());
    }

    // 設備之後改了類別，計畫顯示的類別跟著變，不會留著舊的。
    public function test_the_displayed_category_follows_the_device_when_it_changes(): void
    {
        $device = $this->device();
        $plan = MaintenancePlan::create(['name' => '計畫', 'device_id' => $device->id, 'cycle_days' => 30, 'start_date' => '2026-10-01']);
        $order = MaintenanceOrder::create(['maintenance_plan_id' => $plan->id, 'device_id' => $device->id, 'source' => 'periodic', 'status' => 'pending']);

        $device->update(['device_category_id' => DeviceCategory::create(['name' => '螢幕'])->id]);

        $this->assertSame('螢幕', $plan->fresh()->categoryName());
        $this->assertSame('螢幕', $order->fresh()->categoryName());
    }

    // 沒有設備、只針對一類設備的計畫與工單：類別照常保存與顯示。
    public function test_a_category_wide_plan_and_order_keep_their_category(): void
    {
        $category = DeviceCategory::create(['name' => '交換器']);
        $plan = MaintenancePlan::create(['name' => '計畫', 'device_category_id' => $category->id, 'cycle_days' => 30, 'start_date' => '2026-10-01']);
        $order = MaintenanceOrder::create(['maintenance_plan_id' => $plan->id, 'device_category_id' => $category->id, 'source' => 'periodic', 'status' => 'pending']);

        $this->assertSame($category->id, $plan->fresh()->device_category_id);
        $this->assertSame($category->id, $order->fresh()->device_category_id);
        $this->assertSame('交換器', $order->fresh()->categoryName());
    }

    // 後來才幫計畫指定設備：類別會被清掉。
    public function test_binding_a_device_later_clears_the_stored_category(): void
    {
        $category = DeviceCategory::create(['name' => '交換器']);
        $plan = MaintenancePlan::create(['name' => '計畫', 'device_category_id' => $category->id, 'cycle_days' => 30, 'start_date' => '2026-10-01']);

        $plan->update(['device_id' => $this->device('投影機')->id]);

        $this->assertNull($plan->fresh()->device_category_id);
        $this->assertSame('投影機', $plan->fresh()->categoryName());
    }
}
