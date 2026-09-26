<?php

namespace Tests\Feature;

use App\Models\RepairLog;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_log_belongs_to_a_repair_request(): void
    {
        $repairRequest = RepairRequest::factory()->create();
        $log = RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $this->assertTrue($log->repairRequest->is($repairRequest));
        $this->assertTrue($repairRequest->repairLogs->contains($log));
    }

    public function test_repair_log_requires_a_valid_repair_request(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        RepairLog::factory()->create(['repair_request_id' => 999999]);
    }

    public function test_deleting_repair_request_cascades_to_its_logs(): void
    {
        $repairRequest = RepairRequest::factory()->create();
        RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $repairRequest->delete();

        $this->assertDatabaseCount('repair_logs', 0);
    }
}
