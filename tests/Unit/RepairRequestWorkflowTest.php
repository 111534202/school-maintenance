<?php

namespace Tests\Unit;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\DeviceStatusSync;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 報修單狀態機（App\Services\RepairRequestWorkflow）的單元測試：只測「規則」，不透過網頁。
// 流程：新報修 → 已派工 → 處理中 → 待驗收 → 已結案；驗收不通過可退回處理中。
class RepairRequestWorkflowTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;

    // 輔助方法：建立一個狀態機物件（設備狀態同步用現有的類別）。
    private function workflow(): RepairRequestWorkflow
    {
        return new RepairRequestWorkflow(new DeviceStatusSync());
    }

    // 輔助方法：建立一台可供報修單關聯的測試設備。
    private function makeDevice(array $attrs = []): Device
    {
        $department = Department::create(['name' => '資訊組']);
        $classroom = Classroom::create([
            'department_id' => $department->id,
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '1',
            'classroom_code' => 'A101',
            'name' => 'A101 教室',
        ]);
        $category = DeviceCategory::create(['name' => '測試設備類別']);

        return Device::create([
            'device_code' => 'DEV-WORKFLOW-TEST',
            'device_category_id' => $category->id,
            'brand' => '測試品牌',
            'model' => '測試型號',
            'classroom_id' => $classroom->id,
            'status' => 'normal',
            ...$attrs,
        ]);
    }

    // 合法的完整路徑：新報修一路走到已結案都成功。
    public function test_full_legal_path_pending_to_completed(): void
    {
        $device = $this->makeDevice(['is_core' => true]);
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'pending',
            'device_id' => $device->id,
        ]);
        $workflow = $this->workflow();

        $this->assertSame('normal', $device->fresh()->status);

        $technician = User::factory()->create();
        $workflow->assign($repairRequest, $technician->id, null);
        $this->assertSame(RepairRequestStatus::Assigned, $repairRequest->fresh()->status);

        $workflow->start($repairRequest);
        $this->assertSame(
            'abnormal',
            $device->classroom()->firstOrFail()->fresh()->reservation_status
        );
        $this->assertSame(RepairRequestStatus::InProgress, $repairRequest->fresh()->status);
        $this->assertSame('repairing', $device->fresh()->status);

        $workflow->submitForReview($repairRequest);
        $this->assertSame(
            'abnormal',
            $device->classroom()->firstOrFail()->fresh()->reservation_status
        );
        $this->assertSame(RepairRequestStatus::PendingReview, $repairRequest->fresh()->status);
        $this->assertSame('repairing', $device->fresh()->status);

        $workflow->transitionTo($repairRequest, RepairRequestStatus::Completed);
        $this->assertSame(RepairRequestStatus::Completed, $repairRequest->fresh()->status);
        $this->assertSame('normal', $device->fresh()->status);
        $this->assertSame(
            'normal',
            $device->classroom()->firstOrFail()->fresh()->reservation_status
        );
    }


    // 設備狀態同步失敗時，報修單狀態也必須回滾。
    public function test_start_rolls_back_repair_status_when_device_sync_fails(): void
    {
        $device = $this->makeDevice();

        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'device_id' => $device->id,
        ]);

        $sync = $this->createMock(DeviceStatusSync::class);

        $sync->expects($this->once())
            ->method('markUnderRepair')
            ->willThrowException(new \RuntimeException('模擬設備同步失敗'));

        $workflow = new RepairRequestWorkflow($sync);

        try {
            $workflow->start($repairRequest);
            $this->fail('設備同步失敗時，應該拋出例外。');
        } catch (\RuntimeException $exception) {
            $this->assertSame('模擬設備同步失敗', $exception->getMessage());
        }

        $this->assertSame(
            RepairRequestStatus::Assigned,
            $repairRequest->fresh()->status,
            '設備同步失敗後，報修單狀態應回滾為已派工。'
        );

        $this->assertSame(
            'normal',
            $device->fresh()->status,
            '設備同步失敗後，設備狀態應維持正常。'
        );
    }

    // 待驗收可以被退回「處理中」。
    public function test_start_rejects_pending_review_and_keeps_status_unchanged(): void
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'pending_review',
        ]);

        $thrown = false;

        try {
            $this->workflow()->start($repairRequest);
        } catch (DomainException $exception) {
            $thrown = true;
        }

        $this->assertTrue($thrown, '待驗收案件不得直接呼叫 start()。');
        $this->assertSame(
            RepairRequestStatus::PendingReview,
            $repairRequest->fresh()->status
        );
    }
    public function test_pending_review_can_be_rejected_back_to_in_progress(): void
    {
        // Week1 決策#3：驗收不通過退回「處理中」，不是退回「已派工」。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $this->workflow()->transitionTo($repairRequest, RepairRequestStatus::InProgress);

        $this->assertSame(RepairRequestStatus::InProgress, $repairRequest->fresh()->status);
    }

    // 不能跳過狀態（例如新報修不能直接變處理中）。
    public function test_cannot_skip_states(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);

        $this->expectException(DomainException::class);

        // pending 不能直接跳到 in_progress，必須先經過 assigned。
        $this->workflow()->transitionTo($repairRequest, RepairRequestStatus::InProgress);
    }

    // 已結案是終點，不能再轉去任何狀態。
    public function test_completed_is_a_terminal_state(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'completed']);

        $this->assertFalse($this->workflow()->canTransition($repairRequest, RepairRequestStatus::PendingReview));
    }

    /**
     * 依《第四週個人工作計畫》第 3 項「完整檢查...禁止無效跳轉」：
     * 這裡不是只挑幾個例子測，而是窮舉「5 個狀態 x 5 個狀態」共 25 種組合，
     * 一一驗證合法/不合法的判斷跟規格文件寫的完全一致，不會漏掉任何一種跳法。
     */
    // 把所有「從 A 狀態到 B 狀態」的組合都試一遍，結果必須和轉換規則表完全一致。
    public function test_every_status_pair_matches_the_documented_transition_table(): void
    {
        // key 是「目前狀態」，value 是「唯一允許轉過去」的狀態清單，
        // 這份表要跟 RepairRequestWorkflow::TRANSITIONS 保持一致（那邊是 private
        // 常數，所以這裡用同樣的內容重新描述一次規格，而不是直接讀取它，這樣才能
        // 真的驗證「行為」符合規格，而不是驗證「程式碼抄自己」）。
        $expectedAllowed = [
            'pending' => ['assigned'],
            'assigned' => ['in_progress'],
            'in_progress' => ['pending_review'],
            'pending_review' => ['completed', 'in_progress'],
            'completed' => [],
        ];

        $workflow = $this->workflow();

        foreach (RepairRequestStatus::cases() as $from) {
            $repairRequest = RepairRequest::factory()->create(['status' => $from->value]);

            foreach (RepairRequestStatus::cases() as $to) {
                $shouldBeAllowed = in_array($to->value, $expectedAllowed[$from->value], true);

                $this->assertSame(
                    $shouldBeAllowed,
                    $workflow->canTransition($repairRequest, $to),
                    "「{$from->value}」轉「{$to->value}」的合法性判斷跟規格不符。"
                );
            }
        }
    }

    // 已派工或處理中時，可以重新指派。
    public function test_reassign_works_when_assigned_or_in_progress(): void
    {
        // 依《第四週個人工作計畫》第 1 項：已派工／處理中都能重新指派，不影響狀態本身。
        $originalTechnician = User::factory()->create();
        $newTechnician = User::factory()->create();
        foreach (['assigned', 'in_progress'] as $status) {
            $repairRequest = RepairRequest::factory()->create([
                'status' => $status,
                'assigned_to' => $originalTechnician->id,
            ]);

            $this->workflow()->reassign($repairRequest, $newTechnician->id, null);

            $this->assertSame($newTechnician->id, $repairRequest->fresh()->assigned_to);
            $this->assertSame($status, $repairRequest->fresh()->status->value);
        }
    }

    // 新報修與已結案時，重新指派會被拒絕。
    public function test_reassign_rejects_pending_and_completed_states(): void
    {
        // 注意：expectException() 一次只能驗證「下一個」丟出的例外，丟完程式就會
        // 中斷，所以這裡用 try/catch 逐一驗證每個狀態都真的會擋下來，
        // 而不是只測到迴圈第一輪就結束（曾經在別支測試裡犯過這個錯，這裡刻意避開）。
        $originalTechnician = User::factory()->create();
        $newTechnician = User::factory()->create();
        foreach (['pending', 'pending_review', 'completed'] as $status) {
            $repairRequest = RepairRequest::factory()->create([
                'status' => $status,
                'assigned_to' => $originalTechnician->id,
            ]);

            $threwException = false;
            try {
                $this->workflow()->reassign($repairRequest, $newTechnician->id, null);
            } catch (DomainException $exception) {
                $threwException = true;
            }

            $this->assertTrue($threwException, "狀態「{$status}」應該要擋下重新指派，但沒有丟出例外。");
            $this->assertSame($originalTechnician->id,
                $repairRequest->fresh()->assigned_to,
                "狀態「{$status}」時 assigned_to 不應該被改動。"
            );
        }
    }
}
