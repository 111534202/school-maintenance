<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 保養工單列表的排序：
 * 待處理在最前面（排定日期近→遠、同一天建立早的在上、再依來源計畫名稱 A→Z），
 * 已完成在最後（排定日期遠→近、同一天建立晚的在上）。
 */
class MaintenanceOrderSortTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    public function test_pending_orders_come_first_then_completed_in_the_requested_order(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 09:00:00'));
        $this->actingAs($this->makeUser('admin'));
        $device = $this->makeDevice();
        $planB = $this->makePlan($device, ['name' => 'B 計畫']);
        $planA = $this->makePlan($device, ['name' => 'A 計畫']);

        $make = function (string $code, $plan, string $status, string $date, string $createdAt) use ($device) {
            $order = MaintenanceOrder::create([
                'maintenance_plan_id' => $plan->id,
                'device_id' => $device->id,
                'device_category_id' => $device->device_category_id,
                'source' => MaintenanceOrder::SOURCE_PERIODIC,
                'status' => $status,
                'scheduled_date' => $date,
            ]);
            $order->forceFill(['created_at' => $createdAt])->save();

            return [$code, $order->id];
        };

        $p = MaintenanceOrder::STATUS_PENDING;
        $c = MaintenanceOrder::STATUS_COMPLETED;
        $rows = [
            $make('done-old', $planA, $c, '2026-09-05', '2026-11-01 10:00'),
            $make('pend-far', $planA, $p, '2026-12-26', '2026-11-01 10:00'),
            $make('done-new-early', $planA, $c, '2026-10-05', '2026-11-01 10:00'),
            $make('pend-near-B', $planB, $p, '2026-12-05', '2026-11-02 10:00'),
            $make('pend-near-late', $planA, $p, '2026-12-05', '2026-11-03 10:00'),
            $make('done-new-late', $planA, $c, '2026-10-05', '2026-11-02 10:00'),
            $make('pend-near-A', $planA, $p, '2026-12-05', '2026-11-02 10:00'),
        ];
        $ids = collect($rows)->mapWithKeys(fn ($r) => [$r[1] => $r[0]]);

        $orders = $this->get(route('maintenance-orders.index'))->assertOk()->viewData('orders');

        $this->assertSame([
            'pend-near-A',      // 12-05、11-02 建立、A 計畫
            'pend-near-B',      // 12-05、11-02 建立、B 計畫
            'pend-near-late',   // 12-05、11-03 建立
            'pend-far',         // 12-26
            'done-new-late',    // 10-05、11-02 建立（晚的在上）
            'done-new-early',   // 10-05、11-01 建立
            'done-old',         // 09-05
        ], collect($orders->items())->map(fn ($o) => $ids[$o->id])->all());
    }
}
