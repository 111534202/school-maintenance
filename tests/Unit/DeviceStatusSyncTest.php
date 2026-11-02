<?php

namespace Tests\Unit;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Services\DeviceStatusSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;

    private DeviceStatusSync $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = new DeviceStatusSync();

        $this->room = Classroom::create([
            'campus' => '本',
            'building' => 'A',
            'floor' => '1',
            'classroom_code' => 'A1',
            'name' => 'A1',
        ]);
    }

    private function makeDevice(
        string $code,
        string $status = 'normal'
    ): Device {
        $category = DeviceCategory::firstOrCreate([
            'name' => '投影機',
        ]);

        return Device::create([
            'device_code' => $code,
            'device_category_id' => $category->id,
            'classroom_id' => $this->room->id,
            'status' => $status,
            'is_core' => false,
        ]);
    }

    private function makeRepair(
        Device $device,
        string $status
    ): RepairRequest {
        return RepairRequest::factory()->create([
            'device_id' => $device->id,
            'status' => $status,
        ]);
    }

    public function test_any_device_is_marked_repairing_regardless_of_core_flag(): void
    {
        $device = $this->makeDevice('D-1');

        $repair = $this->makeRepair($device, 'in_progress');

        $this->sync->markUnderRepair($repair);

        $this->assertSame('repairing', $device->fresh()->status);
    }

    public function test_repair_without_device_does_not_throw(): void
    {
        $repair = RepairRequest::factory()->create([
            'device_id' => null,
            'status' => 'in_progress',
        ]);

        $this->sync->markUnderRepair($repair);
        $this->sync->markNormalIfNoActiveRepairs($repair);

        $this->assertDatabaseHas('repair_requests', [
            'id' => $repair->id,
            'device_id' => null,
        ]);
    }

    public function test_retired_and_disabled_devices_are_not_changed_to_repairing(): void
    {
        foreach (['retired', 'disabled'] as $status) {
            $device = $this->makeDevice('D-' . $status, $status);
            $repair = $this->makeRepair($device, 'in_progress');

            $this->sync->markUnderRepair($repair);

            $this->assertSame($status, $device->fresh()->status);
        }
    }

    public function test_device_stays_repairing_while_repair_is_pending_review(): void
    {
        $device = $this->makeDevice('D-1', 'repairing');

        $repair = $this->makeRepair($device, 'pending_review');

        $this->sync->markNormalIfNoActiveRepairs($repair);

        $this->assertSame('repairing', $device->fresh()->status);
    }

    public function test_device_returns_to_normal_when_no_other_open_repairs_exist(): void
    {
        $device = $this->makeDevice('D-1', 'repairing');

        $repair = $this->makeRepair($device, 'completed');

        $this->sync->markNormalIfNoActiveRepairs($repair);

        $this->assertSame('normal', $device->fresh()->status);
    }

    public function test_device_stays_repairing_when_another_open_repair_exists(): void
    {
        $device = $this->makeDevice('D-1', 'repairing');

        $currentRepair = $this->makeRepair($device, 'completed');

        $this->makeRepair($device, 'assigned');

        $this->sync->markNormalIfNoActiveRepairs($currentRepair);

        $this->assertSame('repairing', $device->fresh()->status);
    }

    public function test_completed_other_repairs_do_not_block_returning_to_normal(): void
    {
        $device = $this->makeDevice('D-1', 'repairing');

        $currentRepair = $this->makeRepair($device, 'completed');

        $this->makeRepair($device, 'completed');

        $this->sync->markNormalIfNoActiveRepairs($currentRepair);

        $this->assertSame('normal', $device->fresh()->status);
    }

    public function test_retired_and_disabled_devices_are_not_reset_to_normal(): void
    {
        foreach (['retired', 'disabled'] as $status) {
            $device = $this->makeDevice('D-' . $status, $status);

            $repair = $this->makeRepair($device, 'completed');

            $this->sync->markNormalIfNoActiveRepairs($repair);

            $this->assertSame($status, $device->fresh()->status);
        }
    }
}