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

    /**
     * 依《第四週個人工作計畫》第 3 項「完整檢查...禁止無效跳轉」：
     * 這裡不是只挑幾個例子測，而是窮舉「5 個狀態 x 5 個狀態」共 25 種組合，
     * 一一驗證合法/不合法的判斷跟規格文件寫的完全一致，不會漏掉任何一種跳法。
     */
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

    public function test_reassign_works_when_assigned_or_in_progress(): void
    {
        // 依《第四週個人工作計畫》第 1 項：已派工／處理中都能重新指派，不影響狀態本身。
        foreach (['assigned', 'in_progress'] as $status) {
            $repairRequest = RepairRequest::factory()->create([
                'status' => $status,
                'assignee_note' => '王小明',
            ]);

            $this->workflow()->reassign($repairRequest, '劉小華', null);

            $this->assertSame('劉小華', $repairRequest->fresh()->assignee_note);
            $this->assertSame($status, $repairRequest->fresh()->status->value);
        }
    }

    public function test_reassign_rejects_pending_and_completed_states(): void
    {
        // 注意：expectException() 一次只能驗證「下一個」丟出的例外，丟完程式就會
        // 中斷，所以這裡用 try/catch 逐一驗證每個狀態都真的會擋下來，
        // 而不是只測到迴圈第一輪就結束（曾經在別支測試裡犯過這個錯，這裡刻意避開）。
        foreach (['pending', 'pending_review', 'completed'] as $status) {
            $repairRequest = RepairRequest::factory()->create([
                'status' => $status,
                'assignee_note' => '王小明',
            ]);

            $threwException = false;
            try {
                $this->workflow()->reassign($repairRequest, '劉小華', null);
            } catch (DomainException $exception) {
                $threwException = true;
            }

            $this->assertTrue($threwException, "狀態「{$status}」應該要擋下重新指派，但沒有丟出例外。");
            $this->assertSame('王小明', $repairRequest->fresh()->assignee_note, "狀態「{$status}」時 assignee_note 不應該被改動。");
        }
    }
}
