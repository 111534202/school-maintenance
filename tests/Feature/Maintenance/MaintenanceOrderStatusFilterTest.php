<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 保養工單的「狀態」篩選跟列表的狀態標籤一致：已完成只算結果 OK 的；結果 NG 的分成「待轉報修」「已轉報修」。
 * 已回報的排序也依狀態：待轉報修 → 已轉報修 → 已完成。右下角「匯出 Excel」先開預覽視窗（跟設備、報修等列表一樣）。
 */
class MaintenanceOrderStatusFilterTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    public function test_status_filter_separates_ok_ng_pending_and_ng_converted(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $device = $this->makeDevice();
        $plan = $this->makePlan($device);

        $make = function (?string $result, ?string $conversion = null) use ($device, $plan) {
            $order = MaintenanceOrder::create([
                'maintenance_plan_id' => $plan->id, 'device_id' => $device->id, 'device_category_id' => $device->device_category_id,
                'source' => MaintenanceOrder::SOURCE_PERIODIC, 'status' => $result ? MaintenanceOrder::STATUS_COMPLETED : MaintenanceOrder::STATUS_PENDING,
                'scheduled_date' => now()->addDays(3)->toDateString(),
            ]);
            if ($result) {
                MaintenanceResult::factory()->create(['maintenance_order_id' => $order->id, 'result' => $result, 'ng_conversion_status' => $conversion]);
            }

            return $order->id;
        };
        $pending = $make(null);
        $ok = $make(MaintenanceResult::RESULT_OK);
        $ngWaiting = $make(MaintenanceResult::RESULT_NG, MaintenanceResult::NG_CONVERSION_PENDING);
        $ngDone = $make(MaintenanceResult::RESULT_NG, MaintenanceResult::NG_CONVERSION_CONVERTED);

        $ids = fn (string $status) => $this->get(route('maintenance-orders.index', ['status' => $status]))->assertOk()->viewData('orders')->pluck('id')->all();

        $this->assertSame([$pending], $ids('pending'));
        $this->assertSame([$ok], $ids('completed'));
        $this->assertSame([$ngWaiting], $ids('pending_conversion'));
        $this->assertSame([$ngDone], $ids('converted'));

        // 排序：還沒回報的在前；已回報的依狀態 待轉報修 → 已轉報修 → 已完成。
        $all = $this->get(route('maintenance-orders.index'))->viewData('orders')->pluck('id')->all();
        $this->assertSame([$pending, $ngWaiting, $ngDone, $ok], $all);

        $this->get(route('maintenance-orders.index'))
            ->assertSee('<option value="pending_conversion"', false)->assertSee('<option value="converted"', false)
            ->assertSee('id="maintenanceOrderExportModal"', false)
            ->assertSeeInOrder(['class="tb-bottom-actions"', 'data-bs-target="#maintenanceOrderExportModal"'], false);
    }
}
