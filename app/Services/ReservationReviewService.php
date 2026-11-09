<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 教室預約的審核與時段衝突檢查（送出申請、整筆核准／拒絕、分時段審核都用這裡）。
 *
 * 「已核准的時段」包含：時段本身標成 approved，或時段沒有自己的結果、而整筆申請是 approved（舊資料）。
 * 使用時間只看時段明細；每筆申請至少有一個時段（舊資料已由 migration 2026_11_05_010000 補齊）。
 * 同一間教室、同一個時間只能有一個已核准的時段。
 */
class ReservationReviewService
{
    /**
     * 找出和指定時間重疊的「已核准」時段；沒有衝突回傳 null。
     *
     * @return object{class_name: string, reservation_date: string, start_time: string, end_time: string}|null
     */
    public function approvedConflict(int $classroomId, string $date, string $start, string $end, ?int $excludeReservationId = null): ?object
    {
        return DB::table('classroom_reservation_slots as slots')
            ->join('classroom_reservations as reservations', 'reservations.id', '=', 'slots.classroom_reservation_id')
            ->where('reservations.classroom_id', $classroomId)
            ->when($excludeReservationId, fn ($q) => $q->where('reservations.id', '<>', $excludeReservationId))
            ->whereDate('slots.reservation_date', $date)
            ->where('slots.start_time', '<', $end)
            ->where('slots.end_time', '>', $start)
            ->where(fn ($q) => $q->where('slots.status', ClassroomReservation::STATUS_APPROVED)
                ->orWhere(fn ($q) => $q->whereNull('slots.status')->where('reservations.status', ClassroomReservation::STATUS_APPROVED)))
            ->select(['reservations.class_name', 'slots.reservation_date', 'slots.start_time', 'slots.end_time'])
            ->first();
    }

    /** 整筆核准：每個時段都同意。 */
    public function approveAll(ClassroomReservation $reservation, User $reviewer): array
    {
        return $this->review($reservation, null, $reviewer, allDecision: ClassroomReservation::STATUS_APPROVED);
    }

    /** 整筆拒絕：每個時段都拒絕，原因相同。 */
    public function rejectAll(ClassroomReservation $reservation, User $reviewer, string $reason): array
    {
        return $this->review($reservation, null, $reviewer, allDecision: ClassroomReservation::STATUS_REJECTED, allReason: $reason);
    }

    /**
     * 分時段審核。$decisions：時段編號 => ['decision' => approved|rejected, 'reason' => 拒絕原因]。
     * 每個時段都一定要決定（不能只同意兩個、第三個不處理）；被拒絕的時段一定要有原因。
     *
     * 回傳 ['type' => reviewed|already_reviewed|incomplete|unavailable|conflict, ...]：
     * - conflict 會附上 'slots'（衝突的時段文字），整筆都不會寫入。
     */
    public function review(ClassroomReservation $reservation, ?array $decisions, User $reviewer, ?string $allDecision = null, ?string $allReason = null): array
    {
        return DB::transaction(function () use ($reservation, $decisions, $reviewer, $allDecision, $allReason) {
            // 鎖定申請，避免兩位審核人員同時審核。
            $locked = ClassroomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReviewed()) {
                return ['type' => 'already_reviewed'];
            }

            $slots = $locked->slots()->get();

            // 整理每個時段的決定。
            $plan = [];
            foreach ($slots as $slot) {
                $decision = $allDecision ?? ($decisions[$slot->id]['decision'] ?? null);
                $reason = $allReason ?? ($decisions[$slot->id]['reason'] ?? null);

                if (! in_array($decision, [ClassroomReservation::STATUS_APPROVED, ClassroomReservation::STATUS_REJECTED], true)) {
                    return ['type' => 'incomplete', 'slot' => $slot->label()];
                }
                if ($decision === ClassroomReservation::STATUS_REJECTED && blank($reason)) {
                    return ['type' => 'incomplete', 'slot' => $slot->label(), 'missing_reason' => true];
                }

                $plan[] = [$slot, $decision, $decision === ClassroomReservation::STATUS_REJECTED ? trim($reason) : null];
            }

            $approvedSlots = array_filter($plan, fn ($item) => $item[1] === ClassroomReservation::STATUS_APPROVED);

            if ($approvedSlots) {
                // 有要核准的時段：教室必須仍然啟用、沒有暫停預約（設備異常或核心設備等待維修）。
                $classroom = Classroom::query()->whereKey($locked->classroom_id)->lockForUpdate()->first();
                if (! $classroom || ! $classroom->is_active || ClassroomRepairNotices::isSuspended($classroom->id)) {
                    return ['type' => 'unavailable'];
                }

                // 每個要核准的時段都不能和其他已核准的時段重疊。
                $conflicts = [];
                foreach ($approvedSlots as [$slot]) {
                    $conflict = $this->approvedConflict(
                        $locked->classroom_id,
                        $slot->reservation_date->toDateString(),
                        $slot->start_time,
                        $slot->end_time,
                        $locked->id
                    );
                    if ($conflict) {
                        $conflicts[] = $slot->label() . '（已核准給 ' . $conflict->class_name . '）';
                    }
                }
                if ($conflicts) {
                    return ['type' => 'conflict', 'slots' => $conflicts];
                }
            }

            foreach ($plan as [$slot, $decision, $reason]) {
                $slot->update(['status' => $decision, 'rejection_reason' => $reason]);
            }

            $approvedCount = count($approvedSlots);
            $status = match (true) {
                $approvedCount === count($plan) => ClassroomReservation::STATUS_APPROVED,
                $approvedCount === 0 => ClassroomReservation::STATUS_REJECTED,
                default => ClassroomReservation::STATUS_PARTIALLY_APPROVED,
            };

            $locked->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                // 整筆拒絕時把原因也記在申請上（舊畫面與報表沿用）；分時段的原因記在各時段。
                'rejection_reason' => $status === ClassroomReservation::STATUS_REJECTED ? ($allReason ?? $plan[0][2]) : null,
            ]);

            AuditLogger::log('status_changed', $locked, [
                'status' => ['old' => ClassroomReservation::STATUS_PENDING, 'new' => $status],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $locked->reviewed_at->toDateTimeString(),
                'slots' => array_map(fn ($item) => ['slot' => $item[0]->label(), 'decision' => $item[1], 'reason' => $item[2]], $plan),
            ], match ($status) {
                ClassroomReservation::STATUS_APPROVED => '核准空間預約：',
                ClassroomReservation::STATUS_REJECTED => '拒絕空間預約：',
                default => '部分核准空間預約：',
            } . $locked->class_name);

            return ['type' => 'reviewed', 'status' => $status, 'approved' => $approvedCount, 'rejected' => count($plan) - $approvedCount];
        });
    }
}
