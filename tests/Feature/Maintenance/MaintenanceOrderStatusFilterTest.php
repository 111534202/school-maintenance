<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 保養工單的「狀態」篩選：待處理、進行中、已轉報修、已完成，直接照工單狀態（「待轉報修」已拿掉）。
 * 排序也依狀態：待處理 → 進行中 → 已轉報修 → 已完成。右下角「匯出 Excel」先開預覽視窗（跟設備、報修等列表一樣）。
 */
class MaintenanceOrderStatusFilterTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    public function test_status_filter_and_sort_follow_the_order_status(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $device = $this->makeDevice();
        $plan = $this->makePlan($device);

        $make = fn (string $status) => MaintenanceOrder::create([
            'maintenance_plan_id' => $plan->id, 'device_id' => $device->id, 'device_category_id' => $device->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC, 'status' => $status, 'scheduled_date' => now()->addDays(3)->toDateString(),
        ])->id;
        $completed = $make(MaintenanceOrder::STATUS_COMPLETED);
        $converted = $make(MaintenanceOrder::STATUS_CONVERTED);
        $inProgress = $make(MaintenanceOrder::STATUS_IN_PROGRESS);
        $pending = $make(MaintenanceOrder::STATUS_PENDING);

        $ids = fn (string $status) => $this->get(route('maintenance-orders.index', ['status' => $status]))->assertOk()->viewData('orders')->pluck('id')->all();

        $this->assertSame([$pending], $ids('pending'));
        $this->assertSame([$inProgress], $ids('in_progress'));
        $this->assertSame([$converted], $ids('converted'));
        $this->assertSame([$completed], $ids('completed'));

        $all = $this->get(route('maintenance-orders.index'))->viewData('orders')->pluck('id')->all();
        $this->assertSame([$pending, $inProgress, $converted, $completed], $all);

        $this->get(route('maintenance-orders.index'))
            ->assertDontSee('<option value="pending_conversion"', false)->assertDontSee('待轉報修')->assertSee('<option value="converted"', false)
            ->assertSee('id="maintenanceOrderExportModal"', false)
            ->assertSeeInOrder(['class="tb-bottom-actions"', 'data-bs-target="#maintenanceOrderExportModal"'], false);
    }
}
