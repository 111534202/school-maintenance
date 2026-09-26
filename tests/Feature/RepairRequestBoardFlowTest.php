<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairRequestBoardFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_repair_request_with_attachments_stores_them(): void
    {
        Storage::fake('public');

        $payload = [
            'title' => 'A101 投影機燈泡燒壞',
            'device_note' => 'A101 投影機',
            'description' => '畫面全暗，燈泡指示燈閃紅燈。',
            'impact_level' => 'high',
            'affects_class' => '1',
            'attachments' => [UploadedFile::fake()->image('broken.jpg')],
        ];

        $response = $this->post(route('repair-requests.store'), $payload);

        $repairRequest = RepairRequest::firstWhere('title', 'A101 投影機燈泡燒壞');
        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertCount(1, $repairRequest->attachments);
        Storage::disk('public')->assertExists($repairRequest->attachments->first()->disk_path);
    }

    public function test_full_board_flow_from_pending_to_pending_review(): void
    {
        Storage::fake('public');

        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);

        // 派工
        $this->post(route('repair-requests.assign', $repairRequest), [
            'assignee_note' => '王小明',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertSame('assigned', $repairRequest->fresh()->status->value);

        // 開始處理
        $this->post(route('repair-requests.start', $repairRequest))
            ->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);

        // 填寫維修紀錄
        $response = $this->post(route('repair-logs.store', $repairRequest), [
            'cause' => '燈泡使用超過壽命',
            'resolution' => '更換新燈泡並測試投影正常',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ended_at' => now()->format('Y-m-d\TH:i'),
            'attachments' => [UploadedFile::fake()->image('after.jpg')],
        ]);
        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertSame('pending_review', $repairRequest->fresh()->status->value);
        $this->assertCount(1, $repairRequest->fresh()->repairLogs);
        $this->assertCount(1, $repairRequest->fresh()->repairLogs->first()->attachments);
        // 迴歸測試：total_hours 曾經因為 Carbon diffInMinutes() 方向算反而變成負數，
        // 這裡明確驗證是正的 1 小時（started_at 是 ended_at 往前推 1 小時）。
        $this->assertEquals(1.0, (float) $repairRequest->fresh()->repairLogs->first()->total_hours);
    }

    public function test_cannot_start_a_request_that_has_not_been_assigned_yet(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);

        // pending 案件還沒派工，不能直接「開始處理」（跳過 assigned 這一步）。
        // Controller 會接住 DomainException，導回詳細頁顯示錯誤，不是噴 500。
        $response = $this->post(route('repair-requests.start', $repairRequest));

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame('pending', $repairRequest->fresh()->status->value);
    }

    public function test_resubmitting_assign_on_an_already_assigned_request_does_not_overwrite_it(): void
    {
        // 迴歸測試：曾經的 bug 是 assign() 先寫 assignee_note 才檢查狀態合不合法，
        // 導致重複送出（已經是 assigned 的案件再叫一次 assign）會把 assignee_note
        // 悄悄覆蓋成新值，即使操作因為狀態不合法而失敗。現在應該完全不寫入。
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'assignee_note' => '原本的王小明',
        ]);

        $response = $this->post(route('repair-requests.assign', $repairRequest), [
            'assignee_note' => '惡意重送-李四',
        ]);

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame('原本的王小明', $repairRequest->fresh()->assignee_note);
        $this->assertSame('assigned', $repairRequest->fresh()->status->value);
    }

    public function test_resubmitting_repair_log_on_a_request_not_in_progress_leaves_no_orphan_log(): void
    {
        // 迴歸測試：曾經的 bug 是先建立 repair_log（可能還帶附件）才檢查狀態，
        // 若案件已經是 pending_review（例如重複送出同一份表單），會留下一筆孤兒
        // repair_log，案件狀態卻沒有改變。現在應該完全不寫入任何東西。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repair-logs.store', $repairRequest), [
            'cause' => '重複送出測試',
            'resolution' => '不應該被寫入',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ended_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertCount(0, $repairRequest->fresh()->repairLogs);
    }

    public function test_reviewer_can_approve_a_pending_review_case_to_completed(): void
    {
        // 依《第三週個人工作計畫》第 2 項：報修人驗收通過，案件變成已結案。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repair-requests.complete', $repairRequest));

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertSame('completed', $repairRequest->fresh()->status->value);
    }

    public function test_reviewer_can_reject_a_pending_review_case_back_to_in_progress(): void
    {
        // 依《第三週個人工作計畫》第 3 項：驗收不通過退回「處理中」（不是退回「已派工」），
        // 且要記錄退回原因，讓維修人員知道還要補做什麼。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repair-requests.reject', $repairRequest), [
            'rejection_reason' => '開機還是會自動關機，沒有真的修好。',
        ]);

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);
        $this->assertSame('開機還是會自動關機，沒有真的修好。', $repairRequest->fresh()->rejection_reason);
    }

    public function test_rejecting_does_not_delete_existing_repair_logs(): void
    {
        // 「退回後可再處理，不遺失原 repair_logs」——退回是狀態改變，不是刪除歷史紀錄。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);
        \App\Models\RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $this->post(route('repair-requests.reject', $repairRequest), [
            'rejection_reason' => '還有問題',
        ]);

        $this->assertCount(1, $repairRequest->fresh()->repairLogs);
    }

    public function test_reject_requires_a_reason(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repair-requests.reject', $repairRequest), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending_review', $repairRequest->fresh()->status->value);
    }

    public function test_cannot_complete_a_case_that_is_not_pending_review(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'in_progress']);

        $response = $this->post(route('repair-requests.complete', $repairRequest));

        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);
    }

    public function test_index_shows_active_case_count_for_each_assignee(): void
    {
        // 看板資訊補強（第三週第 6 項）：同一個維修人員名下還有幾張未結案案件，
        // 只顯示客觀數字，不做自動派工推薦。
        RepairRequest::factory()->create(['status' => 'assigned', 'assignee_note' => '王小明']);
        RepairRequest::factory()->create(['status' => 'in_progress', 'assignee_note' => '王小明']);
        RepairRequest::factory()->create(['status' => 'completed', 'assignee_note' => '王小明']);

        $response = $this->get(route('repair-requests.index'));

        // 已結案的那筆不算「未結案」，所以王小明應該顯示還有 2 件（不是 3 件）。
        $response->assertSee('手上還有 2 件未結案', false);
    }

    public function test_index_can_filter_by_status_and_location(): void
    {
        RepairRequest::factory()->create(['status' => 'pending', 'title' => '待處理案件', 'location' => 'A101']);
        RepairRequest::factory()->create(['status' => 'completed', 'title' => '已結案案件', 'location' => 'B203']);

        $response = $this->get(route('repair-requests.index', ['status' => 'pending']));
        $response->assertSee('待處理案件');
        $response->assertDontSee('已結案案件');

        $response = $this->get(route('repair-requests.index', ['location' => 'A101']));
        $response->assertSee('待處理案件');
        $response->assertDontSee('已結案案件');
    }
}
