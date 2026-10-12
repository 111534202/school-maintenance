<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\RepairLog;
use App\Models\RepairPartUsage;
use App\Models\RepairRequest;
use App\Services\RepairRequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class RepairLogPartUsageTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // 使用系統管理員登入，避免測試被權限擋下。
        $this->loginAsAnyUser();
    }

    /**
     * 建立一張已進入「處理中」狀態的報修單。
     *
     * 工作流程必須是 assigned -> in_progress，
     * 不能直接將 pending 當成可填寫維修紀錄的案件。
     */
    private function makeInProgressRequest(): RepairRequest
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
        ]);

        app(RepairRequestWorkflow::class)->start($repairRequest);

        return $repairRequest->fresh();
    }

    /**
     * 建立測試零件。
     */
    private function makePart(int $stock): Part
    {
        return Part::create([
            'name' => '測試 HDMI 線',
            'specification' => '2 公尺',
            'unit_price' => 150,
            'current_stock' => $stock,
            'safety_stock' => 1,
        ]);
    }

    /**
     * 共用一份有效的維修紀錄表單資料。
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'cause' => '測試故障原因',
            'resolution' => '測試維修處理方式',
            'started_at' => '2026-10-12T09:00',
            'ended_at' => '2026-10-12T10:00',
            'parts_used_note' => null,
        ], $overrides);
    }

    /**
     * 正常使用零件時，應建立紀錄與明細、扣除庫存並進入待驗收。
     */
    public function test_submitting_repair_log_records_part_usage_and_deducts_stock(): void
    {
        $repairRequest = $this->makeInProgressRequest();
        $part = $this->makePart(10);

        $response = $this->post(
            route('repair-logs.store', $repairRequest),
            $this->validPayload([
                'part_usages' => [
                    ['part_id' => $part->id, 'quantity' => 3],
                ],
            ])
        );

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('repair_logs', [
            'repair_request_id' => $repairRequest->id,
        ]);

        $repairLog = RepairLog::query()
            ->where('repair_request_id', $repairRequest->id)
            ->sole();

        $this->assertDatabaseHas('repair_part_usage', [
            'repair_log_id' => $repairLog->id,
            'part_id' => $part->id,
            'quantity' => 3,
        ]);

        $this->assertSame(
            7,
            (int) $part->fresh()->current_stock
        );

        $this->assertSame(
            'pending_review',
            $repairRequest->fresh()->status->value
        );
    }

    /**
    * 建立維修用料明細時，應保存當下的單價快照。
    */
    public function test_repair_part_usage_preserves_unit_price_snapshot(): void
    {
        $repairRequest = $this->makeInProgressRequest();
        $part = $this->makePart(10);

        // 記錄維修時，零件單價為 150 元。
        $part->update(['unit_price' => 150]);

        $response = $this->post(
            route('repair-logs.store', $repairRequest),
            $this->validPayload([
                'part_usages' => [
                    ['part_id' => $part->id, 'quantity' => 3],
                ],
            ])
        );

        $response->assertRedirect(route('repairs.show', $repairRequest));

        $repairLog = RepairLog::query()
            ->where('repair_request_id', $repairRequest->id)
            ->sole();

        $usage = RepairPartUsage::query()
            ->where('repair_log_id', $repairLog->id)
            ->sole();

        // 明細應保存使用當下的單價，而不是只依賴零件目前的價格。
        $this->assertSame(150, (int) $usage->unit_price);

        // 即使零件目前價格變成 200 元，舊維修用料明細仍應是 150 元。
        $part->update(['unit_price' => 200]);

        $this->assertSame(200, (int) $part->fresh()->unit_price);
        $this->assertSame(150, (int) $usage->fresh()->unit_price);
    }

    /**
     * 庫存不足時，整筆交易都應回滾。
     */
    public function test_insufficient_stock_rolls_back_repair_log_usage_and_status_change(): void
    {
        $repairRequest = $this->makeInProgressRequest();
        $part = $this->makePart(2);

        $response = $this->post(
            route('repair-logs.store', $repairRequest),
            $this->validPayload([
                'part_usages' => [
                    ['part_id' => $part->id, 'quantity' => 3],
                ],
            ])
        );

        $response->assertSessionHasErrors('part_usages.0.quantity');

        $this->assertDatabaseMissing('repair_logs', [
            'repair_request_id' => $repairRequest->id,
        ]);

        $this->assertDatabaseCount('repair_part_usage', 0);

        $this->assertSame(
            2,
            (int) $part->fresh()->current_stock
        );

        $this->assertSame(
            'in_progress',
            $repairRequest->fresh()->status->value
        );
    }

    /**
     * 沒有使用零件時，仍可正常提交維修紀錄。
     */
    public function test_repair_log_can_be_submitted_without_part_usage(): void
    {
        $repairRequest = $this->makeInProgressRequest();
        $part = $this->makePart(10);

        $response = $this->post(
            route('repair-logs.store', $repairRequest),
            $this->validPayload()
        );

        $response->assertRedirect(route('repairs.show', $repairRequest));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('repair_logs', [
            'repair_request_id' => $repairRequest->id,
        ]);

        $this->assertDatabaseCount('repair_part_usage', 0);

        $this->assertSame(
            10,
            (int) $part->fresh()->current_stock
        );

        $this->assertSame(
            'pending_review',
            $repairRequest->fresh()->status->value
        );
    }

    /**
     * 必填欄位驗證失敗時，不應建立紀錄或扣除庫存。
     */
    public function test_invalid_repair_log_form_does_not_create_log_or_deduct_stock(): void
    {
        $repairRequest = $this->makeInProgressRequest();
        $part = $this->makePart(10);

        $response = $this->post(
            route('repair-logs.store', $repairRequest),
            $this->validPayload([
                'cause' => '',
                'part_usages' => [
                    ['part_id' => $part->id, 'quantity' => 3],
                ],
            ])
        );

        $response->assertSessionHasErrors('cause');

        $this->assertDatabaseMissing('repair_logs', [
            'repair_request_id' => $repairRequest->id,
        ]);

        $this->assertDatabaseCount('repair_part_usage', 0);

        $this->assertSame(
            10,
            (int) $part->fresh()->current_stock
        );

        $this->assertSame(
            'in_progress',
            $repairRequest->fresh()->status->value
        );
    }
}
