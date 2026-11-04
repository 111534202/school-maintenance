<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Http\Requests\StoreRepairLogRequest;
use App\Models\Part;
use App\Models\RepairRequest;
use App\Services\AttachmentUploader;
use App\Services\AuditLogger;
use App\Services\RepairRequestWorkflow;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RepairLogController extends Controller
{
    /**
     * 顯示維修填單表單。
     */
    public function create(RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        Gate::authorize('fillLog', $repairRequest);

        // 只有「處理中」的案件才能填維修紀錄（送出後會轉成待驗收）。已結案、待驗收、還沒開始處理的案件
        // 連表單都不開，不要等使用者填完一大張才在送出時被擋。
        try {
            $workflow->assertCanTransition($repairRequest, RepairRequestStatus::PendingReview);
        } catch (DomainException $exception) {
            return redirect()
                ->route('repairs.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        $parts = Part::query()
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();

        return view(
            'repair-logs.create',
            compact('repairRequest', 'parts')
        );
    }

    /**
     * 儲存維修紀錄、零件使用明細、扣除庫存，
     * 並將報修單推進到待驗收。
     */
    public function store(
        StoreRepairLogRequest $request,
        RepairRequest $repairRequest,
        AttachmentUploader $attachmentUploader,
        RepairRequestWorkflow $workflow
    ) {
        try {
            $workflow->assertCanTransition(
                $repairRequest,
                RepairRequestStatus::PendingReview
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('repairs.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        $validated = $request->validated();

        $startedAt = Carbon::parse($validated['started_at']);
        $endedAt = Carbon::parse($validated['ended_at']);

        $createdLog = null;

        try {
            DB::transaction(function () use (
                $validated,
                $repairRequest,
                $attachmentUploader,
                $workflow,
                $startedAt,
                $endedAt,
                &$createdLog
            ) {
                // 鎖定報修單，並在交易內再次確認狀態。
                $lockedRequest = RepairRequest::query()
                    ->whereKey($repairRequest->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $workflow->assertCanTransition(
                    $lockedRequest,
                    RepairRequestStatus::PendingReview
                );

                // 建立維修紀錄。
                $repairLog = $createdLog = $lockedRequest->repairLogs()->create([
                    'cause' => $validated['cause'],
                    'resolution' => $validated['resolution'],

                    // 保留舊欄位，讓既有資料與舊功能繼續相容。
                    'parts_used_note' => $validated['parts_used_note'] ?? null,

                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'total_hours' => round(
                        ($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 3600,
                        2
                    ),
                ]);

                // 處理正式零件使用明細。
                $partUsages = $validated['part_usages'] ?? [];

                foreach ($partUsages as $index => $usage) {
                    // 空白選單代表這一列沒有選零件，不建立明細。
                    if (empty($usage['part_id'])) {
                        continue;
                    }

                    $partId = (int) $usage['part_id'];
                    $quantity = (int) $usage['quantity'];

                    // 鎖定零件資料，避免同時扣庫存時使用過期的庫存數字。
                    $part = Part::query()
                        ->whereKey($partId)
                        ->lockForUpdate()
                        ->first();

                    if (! $part) {
                        throw ValidationException::withMessages([
                            "part_usages.{$index}.part_id" =>
                                '所選零件不存在，請重新選擇。',
                        ]);
                    }

                    if ($quantity < 1) {
                        throw ValidationException::withMessages([
                            "part_usages.{$index}.quantity" =>
                                '零件使用數量必須至少為 1。',
                        ]);
                    }

                    if ($part->current_stock < $quantity) {
                        throw ValidationException::withMessages([
                            "part_usages.{$index}.quantity" =>
                                "零件「{$part->name}」庫存不足，目前庫存 {$part->current_stock}，需要 {$quantity}。",
                        ]);
                    }

                    // 建立使用明細。
                    $repairLog->partUsages()->create([
                        'part_id' => $part->id,
                        'quantity' => $quantity,
                        'unit_price' => $part->unit_price,
                    ]);

                    // 扣除庫存。
                    $part->current_stock -= $quantity;
                    $part->save();
                }

                // 附件仍交由既有服務處理。
                if (! empty($validated['attachments'])) {
                    $attachmentUploader->storeMany(
                        $repairLog,
                        request()->file('attachments')
                    );
                }

                // 與維修紀錄及庫存變更一起完成狀態轉換。
                $workflow->submitForReview($lockedRequest);
            });
        } catch (DomainException $exception) {
            return redirect()
                ->route('repairs.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        // 只有交易成功後才寫入稽核紀錄。
        AuditLogger::log('created', $createdLog, [
            'repair_request_id' => $repairRequest->id,
            'total_hours' => $createdLog->total_hours,
        ], __('audit.messages.repair_log_created', [
            'title' => $repairRequest->title,
            'hours' => $createdLog->total_hours,
        ]));

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_logs.flash.submitted'));
    }
}
