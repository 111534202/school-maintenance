<?php

namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;
use App\Http\Requests\StoreRepairLogRequest;
use App\Models\Device;
use App\Models\Part;
use App\Models\RepairRequest;
use App\Services\AttachmentUploader;
use App\Services\AuditLogger;
use App\Services\PartStockService;
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

        // 只有「已派工」的案件才能填維修紀錄（送出後會轉成待驗收）。已結案、待驗收、已退回、還沒派工的案件
        // 連表單都不開，不要等使用者填完一大張才在送出時被擋。
        try {
            $workflow->assertCanTransition($repairRequest, RepairRequestStatus::PendingReview);
        } catch (DomainException $exception) {
            return redirect()
                ->route('repairs.show', $repairRequest)
                ->with('error', $exception->getMessage());
        }

        // 零件選單列出全部零件（依零件編號順序）；沒有庫存的也會顯示，但標示庫存 0、不能選。
        $parts = Part::query()
            ->orderBy('id')
            ->get();

        // 報修時沒有設備（例如電燈壞了沒有條碼可掃）：維修人員填紀錄時要補選設備，列出還沒淘汰的設備給他搜尋。
        $devices = $repairRequest->device_id === null
            ? Device::query()->with(['category', 'classroom'])->where('status', '<>', 'retired')->orderBy('device_code')->get()
            : collect();

        return view(
            'repair-logs.create',
            compact('repairRequest', 'parts', 'devices')
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

                // 報修時沒有設備：補上維修人員選的設備（發現地點沒填就用設備所在的教室），並留下操作紀錄。
                if ($lockedRequest->device_id === null && ! empty($validated['device_id'])) {
                    $device = Device::query()->findOrFail((int) $validated['device_id']);
                    $lockedRequest->forceFill([
                        'device_id' => $device->id,
                        'classroom_id' => $lockedRequest->classroom_id ?? $device->classroom_id,
                    ])->save();
                    AuditLogger::log('updated', $lockedRequest, ['device_id' => ['old' => null, 'new' => $device->id]], '維修人員補上報修單的設備：' . $device->device_code);
                }

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

                    // 依先進先出扣庫存：先用最早買進的批次，跨批次時一批建立一列用料明細。
                    try {
                        $allocations = app(PartStockService::class)->consume($part, $quantity);
                    } catch (DomainException $exception) {
                        throw ValidationException::withMessages([
                            "part_usages.{$index}.quantity" => $exception->getMessage(),
                        ]);
                    }

                    foreach ($allocations as $allocation) {
                        $repairLog->partUsages()->create([
                            'part_id' => $part->id,
                            ...$allocation,
                        ]);
                    }

                    // 依日期重新計算先進先出（開始處理時間比較早時，可能改扣更早的批次）；
                    // 若會讓某一筆在當時庫存不足，整筆維修紀錄都不會寫入。
                    try {
                        app(PartStockService::class)->rebuild($part);
                    } catch (DomainException $exception) {
                        throw ValidationException::withMessages([
                            "part_usages.{$index}.quantity" => $exception->getMessage(),
                        ]);
                    }
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
