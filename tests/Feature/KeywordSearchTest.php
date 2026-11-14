<?php

namespace Tests\Feature;

use App\Models\MaintenanceItem;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 報修看板、保養工單、保養計畫、保養項目的關鍵字搜尋。 */
class KeywordSearchTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function ids($response, string $variable): array
    {
        return collect($response->viewData($variable)->items())->pluck('id')->sort()->values()->all();
    }

    public function test_repair_board_searches_title_requester_and_number(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師', ['name' => '林老師']);
        $mine = RepairRequest::factory()->create(['title' => '投影機沒有畫面', 'description' => '測試描述', 'device_note' => null, 'device_id' => null, 'classroom_id' => null, 'requester_id' => $teacher->id]);
        $other = RepairRequest::factory()->create(['title' => '網路斷線', 'description' => '測試描述', 'device_note' => null, 'device_id' => null, 'classroom_id' => null, 'requester_id' => $this->makeUserWithRole('teacher', '教師', ['name' => '黃老師'])->id]);
        $admin = $this->makeUserWithPermissions(['repairs.dispatch']);   // 能派工的人看得到全部
        $this->actingAs($admin);

        $this->assertSame([$mine->id], $this->ids($this->get(route('repairs.index', ['keyword' => '投影機'])), 'repairRequests'));
        $this->assertSame([$other->id], $this->ids($this->get(route('repairs.index', ['keyword' => '黃老師'])), 'repairRequests'));   // 報修人姓名
        $this->assertSame([$mine->id], $this->ids($this->get(route('repairs.index', ['keyword' => '#' . $mine->id])), 'repairRequests'));   // 編號
        $this->assertSame([], $this->ids($this->get(route('repairs.index', ['keyword' => '找不到的字'])), 'repairRequests'));
        $this->get(route('repairs.index'))->assertSee('name="keyword"', false);
    }

    public function test_repair_search_only_covers_cases_the_user_may_see(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        RepairRequest::factory()->create(['title' => '別人的機密報修', 'description' => '測試描述', 'requester_id' => $this->makeUserWithRole('teacher', '教師')->id]);
        $this->actingAs($teacher);

        $this->assertSame([], $this->ids($this->get(route('repairs.index', ['keyword' => '機密'])), 'repairRequests'));   // 搜尋不能繞過權限
    }

    public function test_maintenance_orders_search_plan_assignee_and_number(): void
    {
        $assignee = $this->makeUserWithPermissions(['maintenance.report'], ['name' => '陳師傅']);
        $plan = MaintenancePlan::factory()->create(['name' => '投影機季保養']);
        $a = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'assigned_to' => $assignee->id]);
        $b = MaintenanceOrder::factory()->create();
        $this->actingAs($this->makeUserWithPermissions(['maintenance.view']));

        $this->assertSame([$a->id], $this->ids($this->get(route('maintenance-orders.index', ['keyword' => '季保養'])), 'orders'));
        $this->assertSame([$a->id], $this->ids($this->get(route('maintenance-orders.index', ['keyword' => '陳師傅'])), 'orders'));
        $this->assertSame([$b->id], $this->ids($this->get(route('maintenance-orders.index', ['keyword' => '#' . $b->id])), 'orders'));
    }

    public function test_plans_and_items_have_keyword_search(): void
    {
        MaintenancePlan::factory()->create(['name' => '冷氣濾網清潔']);
        MaintenancePlan::factory()->create(['name' => '網路交換器檢查']);
        MaintenanceItem::factory()->create(['name' => '清潔濾網', 'description' => '拆下洗淨']);
        MaintenanceItem::factory()->create(['name' => '測試電壓', 'description' => '用電表量']);
        $this->actingAs($this->makeUserWithPermissions(['maintenance.view']));

        $plans = collect($this->get(route('maintenance-plans.index', ['keyword' => '冷氣']))->viewData('plans')->items());
        $this->assertSame(['冷氣濾網清潔'], $plans->pluck('name')->all());

        $items = collect($this->get(route('maintenance-items.index', ['keyword' => '洗淨']))->viewData('items')->items());   // 也搜尋說明
        $this->assertSame(['清潔濾網'], $items->pluck('name')->all());

        $this->get(route('maintenance-plans.index', ['keyword' => 'zzz']))->assertSee('找不到符合搜尋條件的保養計畫');
        $this->get(route('maintenance-items.index', ['keyword' => 'zzz']))->assertSee('找不到符合搜尋條件的保養項目');
    }

    // 搜尋與匯出要在「有設備」的資料上也能用（設備資料表沒有名稱欄位，用編號、資產編號、品牌、型號、序號）。
    // 這個測試是在瀏覽器實測發現「搜尋設備時 500」之後補的：之前的測試資料都沒有設備，沒測到設備那一段查詢。
    public function test_searches_work_on_devices_by_code_brand_and_model(): void
    {
        $room = \App\Models\Classroom::create(['campus' => '本', 'building' => 'A', 'floor' => '1', 'classroom_code' => 'A101', 'name' => 'A101 教室']);
        $category = \App\Models\DeviceCategory::create(['name' => '投影機']);
        $device = \App\Models\Device::create(['device_code' => 'PRJ-0042', 'asset_code' => 'AST-77', 'brand' => 'Epson', 'model' => 'EB-X06', 'serial_number' => 'SN-9', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
        $repair = RepairRequest::factory()->create(['title' => '燈泡不亮', 'description' => '測試描述', 'device_note' => null, 'device_id' => $device->id, 'classroom_id' => null]);
        $plan = MaintenancePlan::factory()->create(['name' => '投影機保養', 'device_id' => $device->id, 'device_category_id' => null]);
        $order = MaintenanceOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'device_id' => $device->id]);
        $this->actingAs($this->makeUserWithPermissions(['repairs.dispatch', 'maintenance.view']));

        foreach (['PRJ-0042', 'AST-77', 'Epson', 'EB-X06', 'SN-9'] as $keyword) {
            $this->assertSame([$repair->id], $this->ids($this->get(route('repairs.index', ['keyword' => $keyword])), 'repairRequests'), "報修看板搜尋 {$keyword}");
            $this->assertSame([$order->id], $this->ids($this->get(route('maintenance-orders.index', ['keyword' => $keyword])), 'orders'), "保養工單搜尋 {$keyword}");
        }
        $this->assertSame([$plan->id], collect($this->get(route('maintenance-plans.index', ['keyword' => 'PRJ-0042']))->viewData('plans')->items())->pluck('id')->all());

        // 匯出也要能帶出設備（不能出錯）。
        $this->get(route('repairs.export', ['keyword' => 'Epson']))->assertOk();
        $this->get(route('maintenance-orders.export', ['keyword' => 'Epson']))->assertOk();
    }
}
