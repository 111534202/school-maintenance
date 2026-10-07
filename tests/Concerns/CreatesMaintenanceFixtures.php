<?php

namespace Tests\Concerns;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\Role;
use App\Models\User;

/**
 * 保養／AI 測試共用的假資料產生器。
 * 刻意用最少欄位直接 create，不依賴 seeder，讓每個測試的資料一眼看得懂。
 */
trait CreatesMaintenanceFixtures
{
    protected function makeUser(string $roleSlug = 'admin'): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeDevice(string $code = 'T-001', string $status = 'normal'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '測試電腦']);

        $classroom = Classroom::firstOrCreate(
            ['room_code' => 'R-TEST'],
            ['campus' => '主校區', 'building' => 'A', 'floor' => '1', 'room_name' => '測試教室']
        );

        return Device::create([
            'device_code' => $code,
            'device_category_id' => $category->id,
            'brand' => 'TestBrand',
            'model' => 'TB-1',
            'classroom_id' => $classroom->id,
            'status' => $status,
            'is_core' => false,
        ]);
    }

    protected function makePlan(Device $device, array $overrides = []): MaintenancePlan
    {
        return MaintenancePlan::create(array_merge([
            'name' => '測試月檢計畫 '.$device->device_code,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'cycle_days' => 30,
            'start_date' => now()->subMonths(6)->toDateString(),
            'next_due_date' => now()->addDays(20)->toDateString(),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * 為設備建立一段已完成的保養歷史。
     *
     * @param  string  $pattern  由舊到新，o = OK、n = NG，例如 'ononnn'
     * @param  int  $lastDaysAgo  最後一筆保養是幾天前（每筆之間相隔 30 天）
     */
    protected function addHistory(Device $device, string $pattern, int $lastDaysAgo = 10): void
    {
        $plan = $this->makePlan($device);
        $results = str_split($pattern);
        $count = count($results);

        foreach ($results as $index => $code) {
            $daysAgo = $lastDaysAgo + 30 * ($count - 1 - $index);
            $when = now()->subDays($daysAgo);

            $order = MaintenanceOrder::create([
                'maintenance_plan_id' => $plan->id,
                'device_id' => $device->id,
                'device_category' => $device->category?->name,
                'source' => MaintenanceOrder::SOURCE_PERIODIC,
                'status' => MaintenanceOrder::STATUS_COMPLETED,
                'scheduled_date' => $when->toDateString(),
            ]);

            MaintenanceResult::create([
                'maintenance_order_id' => $order->id,
                'result' => $code === 'n' ? MaintenanceResult::RESULT_NG : MaintenanceResult::RESULT_OK,
                'executed_by' => '測試',
                'executed_at' => $when,
                'ng_conversion_status' => $code === 'n' ? MaintenanceResult::NG_CONVERSION_PENDING : null,
            ]);
        }
    }

    protected function makeOpenOrder(Device $device, int $dueInDays, string $source = MaintenanceOrder::SOURCE_PERIODIC): MaintenanceOrder
    {
        return MaintenanceOrder::create([
            'maintenance_plan_id' => null,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => $source,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => now()->addDays($dueInDays)->toDateString(),
        ]);
    }
}
