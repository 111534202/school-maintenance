<?php

namespace Tests\Unit;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;
use App\Services\DeviceStatusSync;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function workflow(): RepairRequestWorkflow
    {
        return new RepairRequestWorkflow(new DeviceStatusSync());
    }

    public function test_full_legal_path_pending_to_completed(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);
        $workflow = $this->workflow();

        $workflow->assign($repairRequest, '王小明', null);
        $this->assertSame(RepairRequestStatus::Assigned, $repairRequest->fresh()->status);

        $workflow->start($repairRequest);
        $this->assertSame(RepairRequestStatus::InProgress, $repairRequest->fresh()->status);

        $workflow->submitForReview($repairRequest);
        $this->assertSame(RepairRequestStatus::PendingReview, $repairRequest->fresh()->status);

        $workflow->transitionTo($repairRequest, RepairRequestStatus::Completed);
        $this->assertSame(RepairRequestStatus::Completed, $repairRequest->fresh()->status);
    }

    public function test_pending_review_can_be_rejected_back_to_in_progress(): void
    {
        // Week1 決策#3：驗收不通過退回「處理中」，不是退回「已派工」。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $this->workflow()->transitionTo($repairRequest, RepairRequestStatus::InProgress);

        $this->assertSame(RepairRequestStatus::InProgress, $repairRequest->fresh()->status);
    }

    public function test_cannot_skip_states(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);

        $this->expectException(DomainException::class);

        // pending 不能直接跳到 in_progress，必須先經過 assigned。
        $this->workflow()->transitionTo($repairRequest, RepairRequestStatus::InProgress);
    }

    public function test_completed_is_a_terminal_state(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'completed']);

        $this->assertFalse($this->workflow()->canTransition($repairRequest, RepairRequestStatus::PendingReview));
    }
}
