<?php

namespace Tests\Unit;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 報修單狀態機（App\Services\RepairRequestWorkflow）的單元測試：只測「規則」，不透過網頁。
// 流程（依《報修與保養流程修改說明》）：新報修 → 已派工 → 待驗收 → 已結案；驗收不通過退回已派工；
// 新報修、已派工可以退回（已退回，流程結束）。沒有「處理中」，設備狀態也不跟著報修單改寫。
class RepairRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function workflow(): RepairRequestWorkflow
    {
        return new RepairRequestWorkflow();
    }

    private function makeDevice(): Device
    {
        $department = Department::create(['name' => '資訊組']);
        $classroom = Classroom::create([
            'department_id' => $department->id, 'campus' => '本校區', 'building' => 'A棟', 'floor' => '1',
            'classroom_code' => 'A101', 'name' => 'A101 教室',
        ]);
        $category = DeviceCategory::create(['name' => '測試設備類別']);

        return Device::create([
            'device_code' => 'DEV-WORKFLOW-TEST', 'device_category_id' => $category->id,
            'classroom_id' => $classroom->id, 'status' => 'normal',
        ]);
    }

    // 合法的完整路徑：新報修 → 已派工 → 待驗收 → 已結案；設備狀態全程不被改寫（待維修由報修單推算）。
    public function test_full_legal_path_pending_to_completed_without_touching_the_device(): void
    {
        $device = $this->makeDevice();
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending', 'device_id' => $device->id]);
        $workflow = $this->workflow();

        $workflow->assign($repairRequest, User::factory()->create()->id, null);
        $this->assertSame(RepairRequestStatus::Assigned, $repairRequest->fresh()->status);

        $workflow->submitForReview($repairRequest);
        $this->assertSame(RepairRequestStatus::PendingReview, $repairRequest->fresh()->status);

        $workflow->complete($repairRequest);
        $this->assertSame(RepairRequestStatus::Completed, $repairRequest->fresh()->status);
        $this->assertSame('normal', $device->fresh()->status);
    }

    // 驗收不通過：待驗收 → 已派工，記下原因；其他狀態不能用這條路（例如新報修不能被「驗收不通過」推成已派工）。
    public function test_reject_goes_back_to_assigned_and_only_from_pending_review(): void
    {
        $workflow = $this->workflow();
        $review = RepairRequest::factory()->create(['status' => 'pending_review']);
        $workflow->reject($review, '還是壞的');
        $this->assertSame(RepairRequestStatus::Assigned, $review->fresh()->status);
        $this->assertSame('還是壞的', $review->fresh()->rejection_reason);

        $pending = RepairRequest::factory()->create(['status' => 'pending']);
        try {
            $workflow->reject($pending, '亂退');
            $this->fail('新報修不能被驗收不通過。');
        } catch (DomainException) {
            $this->assertSame(RepairRequestStatus::Pending, $pending->fresh()->status);
            $this->assertNull($pending->fresh()->rejection_reason);
        }
    }

    // 派工只能從新報修開始：待驗收不能被「派工」拉回已派工。
    public function test_assign_only_works_from_pending(): void
    {
        $technician = User::factory()->create();
        $review = RepairRequest::factory()->create(['status' => 'pending_review']);

        $this->expectException(DomainException::class);
        try {
            $this->workflow()->assign($review, $technician->id, null);
        } finally {
            $this->assertSame(RepairRequestStatus::PendingReview, $review->fresh()->status);
            $this->assertNull($review->fresh()->assigned_to);
        }
    }

    // 退回：新報修、已派工都可以退回，記下原因、退回人與時間；其他狀態不行。
    public function test_return_works_from_pending_and_assigned_only(): void
    {
        $workflow = $this->workflow();
        $user = User::factory()->create();

        foreach (['pending', 'assigned'] as $status) {
            $repair = RepairRequest::factory()->create(['status' => $status]);
            $workflow->returnRequest($repair, '不需要維修', $user->id);
            $repair->refresh();
            $this->assertSame(RepairRequestStatus::Returned, $repair->status);
            $this->assertSame('不需要維修', $repair->return_reason);
            $this->assertSame($user->id, $repair->returned_by);
            $this->assertNotNull($repair->returned_at);
        }

        foreach (['pending_review', 'completed', 'returned'] as $status) {
            $repair = RepairRequest::factory()->create(['status' => $status]);
            try {
                $workflow->returnRequest($repair, 'x', $user->id);
                $this->fail("{$status} 不應該能退回");
            } catch (DomainException) {
                $this->assertSame($status, $repair->fresh()->status->value);
            }
        }
    }

    // 每一組「目前狀態 → 目標狀態」都要和說明文件的轉換表一致。
    public function test_every_status_pair_matches_the_documented_transition_table(): void
    {
        $allowed = [
            'pending' => ['assigned', 'returned'],
            'assigned' => ['pending_review', 'returned'],
            'pending_review' => ['completed', 'assigned'],
            'returned' => [],
            'completed' => [],
            'aborted' => [],   // 設備淘汰而中止：只有 abort() 一條路進來，人工流程不能轉進也不能轉出
        ];
        $workflow = $this->workflow();

        foreach (RepairRequestStatus::cases() as $from) {
            $repair = RepairRequest::factory()->make(['status' => $from->value]);
            foreach (RepairRequestStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to->value, $allowed[$from->value], true),
                    $workflow->canTransition($repair, $to),
                    "{$from->value} → {$to->value}"
                );
            }
        }
    }

    // 已結案、已退回是終點；只有這兩個算「已結束」。
    public function test_completed_and_returned_are_the_closed_states(): void
    {
        $this->assertEqualsCanonicalizing(['completed', 'returned', 'aborted'], RepairRequestStatus::closedValues());
        $this->assertTrue(RepairRequestStatus::Returned->isClosed());
        $this->assertFalse(RepairRequestStatus::Assigned->isClosed());
        $this->assertSame(['assigned', 'pending_review'], RepairRequestStatus::awaitingRepairValues());
    }

    // 重新指派只限已派工；狀態不變。
    public function test_reassign_only_works_when_assigned(): void
    {
        $workflow = $this->workflow();
        $newTech = User::factory()->create();

        $assigned = RepairRequest::factory()->create(['status' => 'assigned']);
        $workflow->reassign($assigned, $newTech->id, null);
        $this->assertSame($newTech->id, $assigned->fresh()->assigned_to);
        $this->assertSame(RepairRequestStatus::Assigned, $assigned->fresh()->status);

        foreach (['pending', 'pending_review', 'completed', 'returned'] as $status) {
            $repair = RepairRequest::factory()->create(['status' => $status]);
            try {
                $workflow->reassign($repair, $newTech->id, null);
                $this->fail("{$status} 不應該能重新指派");
            } catch (DomainException) {
                $this->assertNotSame($newTech->id, $repair->fresh()->assigned_to);
            }
        }
    }
}
