<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\RepairDispatchedMail;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class RepairRequestBoardFlowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
    }

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

        $response = $this->post(route('repairs.store'), $payload);

        $repairRequest = RepairRequest::firstWhere('title', 'A101 投影機燈泡燒壞');
        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertCount(1, $repairRequest->attachments);
        Storage::disk('public')->assertExists($repairRequest->attachments->first()->disk_path);
    }

    public function test_full_board_flow_from_pending_to_pending_review(): void
    {
        Storage::fake('public');
        Mail::fake();

        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);
        $technician = $this->makeTechnician();

        // 派工
        $this->post(route('repairs.assign', $repairRequest), [
            'assigned_to' => $technician->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame('assigned', $repairRequest->fresh()->status->value);
        $this->assertSame($technician->id, $repairRequest->fresh()->assigned_to);
        Mail::assertSent(RepairDispatchedMail::class, fn ($mail) => $mail->hasTo($technician->email));

        // 開始處理
        $this->post(route('repairs.start', $repairRequest))
            ->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);

        // 填寫維修紀錄
        $response = $this->post(route('repair-logs.store', $repairRequest), [
            'cause' => '燈泡使用超過壽命',
            'resolution' => '更換新燈泡並測試投影正常',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ended_at' => now()->format('Y-m-d\TH:i'),
            'attachments' => [UploadedFile::fake()->image('after.jpg')],
        ]);
        $response->assertRedirect(route('repairs.show', $repairRequest));
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
        $response = $this->post(route('repairs.start', $repairRequest));

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame('pending', $repairRequest->fresh()->status->value);
    }

    public function test_resubmitting_assign_on_an_already_assigned_request_does_not_overwrite_it(): void
    {
        // 迴歸測試：曾經的 bug 是 assign() 先寫 assignee 才檢查狀態合不合法，
        // 導致重複送出（已經是 assigned 的案件再叫一次 assign）會把維修人員
        // 悄悄覆蓋成新值，即使操作因為狀態不合法而失敗。現在應該完全不寫入。
        $originalTechnician = $this->makeTechnician();
        $anotherTechnician = $this->makeTechnician();
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'assigned_to' => $originalTechnician->id,
            'assignee_note' => $originalTechnician->name,
        ]);

        $response = $this->post(route('repairs.assign', $repairRequest), [
            'assigned_to' => $anotherTechnician->id,
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame($originalTechnician->id, $repairRequest->fresh()->assigned_to);
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

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertCount(0, $repairRequest->fresh()->repairLogs);
    }

    public function test_reviewer_can_approve_a_pending_review_case_to_completed(): void
    {
        // 依《第三週個人工作計畫》第 2 項：報修人驗收通過，案件變成已結案。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repairs.complete', $repairRequest));

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame('completed', $repairRequest->fresh()->status->value);
    }

    public function test_reviewer_can_reject_a_pending_review_case_back_to_in_progress(): void
    {
        // 依《第三週個人工作計畫》第 3 項：驗收不通過退回「處理中」（不是退回「已派工」），
        // 且要記錄退回原因，讓維修人員知道還要補做什麼。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repairs.reject', $repairRequest), [
            'rejection_reason' => '開機還是會自動關機，沒有真的修好。',
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);
        $this->assertSame('開機還是會自動關機，沒有真的修好。', $repairRequest->fresh()->rejection_reason);
    }

    public function test_rejecting_does_not_delete_existing_repair_logs(): void
    {
        // 「退回後可再處理，不遺失原 repair_logs」——退回是狀態改變，不是刪除歷史紀錄。
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);
        \App\Models\RepairLog::factory()->create(['repair_request_id' => $repairRequest->id]);

        $this->post(route('repairs.reject', $repairRequest), [
            'rejection_reason' => '還有問題',
        ]);

        $this->assertCount(1, $repairRequest->fresh()->repairLogs);
    }

    public function test_reject_requires_a_reason(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending_review']);

        $response = $this->post(route('repairs.reject', $repairRequest), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending_review', $repairRequest->fresh()->status->value);
    }

    public function test_cannot_complete_a_case_that_is_not_pending_review(): void
    {
        $repairRequest = RepairRequest::factory()->create(['status' => 'in_progress']);

        $response = $this->post(route('repairs.complete', $repairRequest));

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);
    }

    public function test_index_shows_active_case_count_for_each_assignee(): void
    {
        // 看板資訊補強（第三週第 6 項）：同一個維修人員名下還有幾張未結案案件，
        // 只顯示客觀數字，不做自動派工推薦。
        $technician = $this->makeTechnician();
        RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $technician->id]);
        RepairRequest::factory()->create(['status' => 'completed', 'assigned_to' => $technician->id]);

        $response = $this->get(route('repairs.index'));

        // 已結案的那筆不算「未結案」，所以這位技師應該顯示還有 2 件（不是 3 件）。
        $response->assertSee('（未結案 2 件）', false);
    }

    public function test_index_can_filter_by_status_and_location(): void
    {
        RepairRequest::factory()->create(['status' => 'pending', 'title' => '待處理案件', 'location' => 'A101']);
        RepairRequest::factory()->create(['status' => 'completed', 'title' => '已結案案件', 'location' => 'B203']);

        $response = $this->get(route('repairs.index', ['status' => 'pending']));
        $response->assertSee('待處理案件');
        $response->assertDontSee('已結案案件');

        $response = $this->get(route('repairs.index', ['location' => 'A101']));
        $response->assertSee('待處理案件');
        $response->assertDontSee('已結案案件');
    }

    public function test_index_can_filter_by_assignee(): void
    {
        // 依《第四週個人工作計畫》第 1 項新增的維修人員篩選，現在用真正的
        // users.name 比對（assigned_to 關聯），不再是自由文字。
        $technicianA = $this->makeTechnician();
        $technicianB = $this->makeTechnician();
        RepairRequest::factory()->create(['title' => '王小明的案件', 'assigned_to' => $technicianA->id]);
        RepairRequest::factory()->create(['title' => '劉小華的案件', 'assigned_to' => $technicianB->id]);

        $response = $this->get(route('repairs.index', ['assignee' => $technicianA->name]));

        $response->assertSee('王小明的案件');
        $response->assertDontSee('劉小華的案件');
    }

    public function test_can_reassign_an_assigned_case_to_a_different_technician(): void
    {
        // 依《第四週個人工作計畫》第 1 項「重新指派操作」：換人不改變案件狀態。
        Mail::fake();
        $originalTechnician = $this->makeTechnician();
        $newTechnician = $this->makeTechnician();
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'assigned_to' => $originalTechnician->id,
            'assignee_note' => $originalTechnician->name,
        ]);

        $response = $this->post(route('repairs.reassign', $repairRequest), [
            'assigned_to' => $newTechnician->id,
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame($newTechnician->id, $repairRequest->fresh()->assigned_to);
        // 狀態應該維持「已派工」不變，重新指派不是狀態轉換。
        $this->assertSame('assigned', $repairRequest->fresh()->status->value);
        Mail::assertSent(RepairDispatchedMail::class, fn ($mail) => $mail->hasTo($newTechnician->email));
    }

    public function test_can_reassign_an_in_progress_case(): void
    {
        $originalTechnician = $this->makeTechnician();
        $newTechnician = $this->makeTechnician();
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'in_progress',
            'assigned_to' => $originalTechnician->id,
            'assignee_note' => $originalTechnician->name,
        ]);

        $response = $this->post(route('repairs.reassign', $repairRequest), [
            'assigned_to' => $newTechnician->id,
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame($newTechnician->id, $repairRequest->fresh()->assigned_to);
        $this->assertSame('in_progress', $repairRequest->fresh()->status->value);
    }

    public function test_cannot_reassign_a_case_that_has_not_been_assigned_yet(): void
    {
        // 「新報修」還沒派過工，應該走 assign() 而不是 reassign()。
        $technician = $this->makeTechnician();
        $repairRequest = RepairRequest::factory()->create(['status' => 'pending']);

        $response = $this->post(route('repairs.reassign', $repairRequest), [
            'assigned_to' => $technician->id,
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('error');
        $this->assertNull($repairRequest->fresh()->assigned_to);
    }

    public function test_cannot_reassign_a_completed_case(): void
    {
        $originalTechnician = $this->makeTechnician();
        $newTechnician = $this->makeTechnician();
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'completed',
            'assigned_to' => $originalTechnician->id,
            'assignee_note' => $originalTechnician->name,
        ]);

        $response = $this->post(route('repairs.reassign', $repairRequest), [
            'assigned_to' => $newTechnician->id,
        ]);

        $response->assertSessionHas('error');
        // 已結案的案件不能被重新指派，維修人員應該維持原本記錄的人。
        $this->assertSame($originalTechnician->id, $repairRequest->fresh()->assigned_to);
    }

    public function test_repair_log_accepts_a_video_attachment_and_parts_used_note(): void
    {
        // 依《第四週個人工作計畫》第 2、4 項：維修紀錄要能上傳影片、記錄使用備品說明。
        Storage::fake('public');
        $repairRequest = RepairRequest::factory()->create(['status' => 'in_progress']);

        $response = $this->post(route('repair-logs.store', $repairRequest), [
            'cause' => '風扇葉片變形產生異音',
            'resolution' => '更換風扇並錄影確認運轉恢復正常',
            'parts_used_note' => '投影機散熱風扇 x1',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ended_at' => now()->format('Y-m-d\TH:i'),
            'attachments' => [UploadedFile::fake()->create('after.mp4', 500, 'video/mp4')],
        ]);

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $log = $repairRequest->fresh()->repairLogs->first();
        $this->assertSame('投影機散熱風扇 x1', $log->parts_used_note);
        $this->assertCount(1, $log->attachments);
    }

    public function test_repair_request_attachment_rejects_disallowed_file_type(): void
    {
        // 附件驗證：不在允許清單裡的檔案類型（例如 .exe）要被擋下，不能悄悄接受任意檔案。
        $response = $this->post(route('repairs.store'), [
            'title' => '測試不合法附件類型',
            'description' => '測試用',
            'impact_level' => 'low',
            'affects_class' => '0',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload')],
        ]);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseMissing('repair_requests', ['title' => '測試不合法附件類型']);
    }
}
