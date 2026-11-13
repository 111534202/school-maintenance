<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\ClassroomReservationSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 教室預約的審核與時段衝突檢查（送出申請、逐節審核、自動拒絕重疊的時段都用這裡）。
 *
 * 逐節審核：一筆申請可以只同意其中幾節。例如 A 申請週一第 5、6 節，B 申請週一第 5 節：
 * - B 先被同意後，A 的第 5 節就不能再同意（系統自動把 A 的第 5 節拒絕），A 的第 6 節仍可同意。
 * - 申請人送出時如果勾選「其中一節無法借用就整筆不要」（is_all_or_nothing），這筆只能整筆同意或整筆拒絕；
 *   只要有一節被別人借走，整筆自動拒絕。
 *
 * 時間已經過了還沒審核的申請「不會」自動拒絕：維持待審核，之後有權限的人仍可補同意（課表就會從「時間已過」變成「已借用」）。
 * 被拒絕的申請、與時段全部已過還沒審核的申請，每月第二、四個週日跨週一的 0 點自動清除（reservations:cleanup）。
 *
 * 時段的狀態（ClassroomReservationSlot::status）：空白＝還沒審核、approved＝同意、rejected＝拒絕。
 * 整筆申請的狀態由時段決定：還有沒審核的時段＝待審核；全部同意＝已核准；全部拒絕＝已拒絕；有同意也有拒絕＝部分核准。
 * 「已核准的時段」包含：時段本身標成 approved，或時段沒有自己的結果、而整筆申請是 approved（舊資料）。
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

    /**
     * 逐節審核：$decisions 是「時段 id => approved／rejected」，每個還沒審核的時段都要有決定。
     * 拒絕原因（選填）套用在這次被拒絕的時段。
     *
     * 回傳 ['type' => reviewed|already_reviewed|unavailable|conflict|is_all_or_nothing|incomplete, ...]：
     * - reviewed 附上 'status'（整筆的新狀態）與 'auto_rejected'（因此被自動拒絕的其他申請時段數）。
     * - conflict：要同意的時段已核准給別人；附上 'slots'。整筆制的申請這時會整筆自動拒絕（'whole_rejected' => true）。
     */
    public function review(ClassroomReservation $reservation, User $reviewer, array $decisions, ?string $reason = null): array
    {
        $reason = filled($reason) ? trim($reason) : null;

        return DB::transaction(function () use ($reservation, $reviewer, $decisions, $reason) {
            // 鎖定申請，避免兩位審核人員同時審核。
            $locked = ClassroomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $undecided = $locked->slots()->whereNull('status')->get();

            if ($locked->isReviewed() || $undecided->isEmpty()) {
                return ['type' => 'already_reviewed'];
            }

            // 每個還沒審核的時段都要有決定。
            $decide = [];
            foreach ($undecided as $slot) {
                $value = $decisions[$slot->id] ?? null;
                if (! in_array($value, [ClassroomReservation::STATUS_APPROVED, ClassroomReservation::STATUS_REJECTED], true)) {
                    return ['type' => 'incomplete'];
                }
                $decide[$slot->id] = $value;
            }
            $approving = $undecided->filter(fn ($slot) => $decide[$slot->id] === ClassroomReservation::STATUS_APPROVED);

            // 申請人選了「其中一節無法借用就整筆不要」：只能整筆同意或整筆拒絕。
            if ($locked->is_all_or_nothing && $approving->isNotEmpty() && $approving->count() !== $undecided->count()) {
                return ['type' => 'is_all_or_nothing'];
            }

            if ($approving->isNotEmpty()) {
                // 教室必須仍然啟用、沒有暫停預約（設備異常或核心設備等待維修）。
                $classroom = Classroom::query()->whereKey($locked->classroom_id)->lockForUpdate()->first();
                if (! $classroom || ! $classroom->is_active || ClassroomRepairNotices::isSuspended($classroom->id)) {
                    return ['type' => 'unavailable'];
                }

                // 要同意的時段已經核准給別人：不能同意。
                $conflicts = $this->conflictLabels($locked, $approving);
                if ($conflicts) {
                    if ($locked->is_all_or_nothing) {
                        $this->autoReject($locked, $this->conflictReason($conflicts[0], true));

                        return ['type' => 'conflict', 'slots' => array_column($conflicts, 'text'), 'whole_rejected' => true];
                    }

                    return ['type' => 'conflict', 'slots' => array_column($conflicts, 'text'), 'whole_rejected' => false];
                }
            }

            $before = $locked->status;
            foreach ($undecided as $slot) {
                $approved = $decide[$slot->id] === ClassroomReservation::STATUS_APPROVED;
                $slot->update([
                    'status' => $decide[$slot->id],
                    'rejection_reason' => $approved ? null : $reason,
                ]);
            }
            $this->refreshStatus($locked, $reviewer->id, $reason);

            AuditLogger::log('status_changed', $locked, [
                'status' => ['old' => $before, 'new' => $locked->status],
                'approved_slots' => $approving->map(fn ($slot) => $slot->label())->values()->all(),
                'rejected_slots' => $undecided->diff($approving)->map(fn ($slot) => $slot->label())->values()->all(),
                'reviewed_by' => $reviewer->id,
            ], '審核空間預約：' . $locked->class_name . '（' . $locked->statusLabel()[0] . '）');

            // 和剛同意的時段重疊的其他待審核時段：自動拒絕（整筆制的申請整筆拒絕）。
            $autoRejected = $this->rejectOverlapping($locked, $approving);

            return ['type' => 'reviewed', 'status' => $locked->status, 'auto_rejected' => $autoRejected];
        });
    }

    /** 整筆同意：每個還沒審核的時段都同意。 */
    public function approveAll(ClassroomReservation $reservation, User $reviewer): array
    {
        $ids = $reservation->slots()->whereNull('status')->pluck('id');

        return $this->review($reservation, $reviewer, $ids->mapWithKeys(fn ($id) => [$id => ClassroomReservation::STATUS_APPROVED])->all());
    }

    /** 整筆拒絕：每個還沒審核的時段都拒絕，原因相同（原因可以不填）。 */
    public function rejectAll(ClassroomReservation $reservation, User $reviewer, ?string $reason = null): array
    {
        $ids = $reservation->slots()->whereNull('status')->pluck('id');

        return $this->review($reservation, $reviewer, $ids->mapWithKeys(fn ($id) => [$id => ClassroomReservation::STATUS_REJECTED])->all(), $reason);
    }

    /**
     * 自動整理：待審核申請裡和「已核准時段」重疊的時段自動拒絕（整筆制的申請整筆拒絕）。
     * 空間申請列表、預約詳細、空間預約頁與主控台打開時先跑一次，狀態才正確。時間已過的申請不會被拒絕。
     * 回傳這次自動拒絕的時段數。
     */
    public function sweep(): int
    {
        $count = 0;
        $pending = ClassroomReservation::query()->where('status', ClassroomReservation::STATUS_PENDING)->pluck('id');

        foreach ($pending as $id) {
            $count += DB::transaction(function () use ($id) {
                $locked = ClassroomReservation::query()->whereKey($id)->lockForUpdate()->first();
                if (! $locked || $locked->isReviewed()) {
                    return 0;
                }
                $conflicts = $this->conflictSlots($locked, $locked->slots()->whereNull('status')->get());
                if (! $conflicts) {
                    return 0;
                }

                return $this->rejectConflictingSlots($locked, $conflicts);
            });
        }

        return $count;
    }

    /**
     * 每月第二、四個週日跨週一 0 點的清除：刪掉「已拒絕」的申請，以及時段全部已經開始、還沒審核完的申請。
     * 部分核准、已核准的申請保留。回傳刪除的筆數。
     */
    public function cleanup(): int
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        $ids = ClassroomReservation::query()
            ->where(fn ($q) => $q->where('status', ClassroomReservation::STATUS_REJECTED)
                ->orWhere(fn ($q) => $q->where('status', ClassroomReservation::STATUS_PENDING)
                    // 還有沒開始的時段就不刪（之後還可能審核）。
                    ->whereDoesntHave('slots', fn ($s) => $s->where(fn ($s) => $s->whereDate('reservation_date', '>', $today)
                        ->orWhere(fn ($s) => $s->whereDate('reservation_date', $today)->where('start_time', '>', $time))))))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($ids) {
            ClassroomReservationSlot::query()->whereIn('classroom_reservation_id', $ids)->delete();
            ClassroomReservation::query()->whereKey($ids)->delete();
        });

        AuditLogger::log('deleted', null, ['count' => $ids->count()], '自動清除已拒絕與逾期未審核的空間申請：' . $ids->count() . ' 筆');

        return $ids->count();
    }

    /**
     * 這筆申請的時段裡，和其他已核准時段重疊的（給畫面與錯誤訊息用）。
     *
     * @return list<array{slot: string, class_name: string, text: string}>
     */
    public function conflictLabels(ClassroomReservation $reservation, ?Collection $slots = null): array
    {
        return array_map(fn ($c) => [
            'slot' => $c['slot']->label(),
            'class_name' => $c['class_name'],
            'text' => $c['slot']->label() . '（已核准給 ' . $c['class_name'] . '）',
        ], $this->conflictSlots($reservation, $slots ?? $reservation->slots));
    }

    /**
     * 同一間教室裡，和這筆申請的時段重疊、還沒審核的其他時段（同意後會被自動拒絕）。
     *
     * @return list<array{reservation: ClassroomReservation, slot: string}>
     */
    public function overlappingPending(ClassroomReservation $reservation, ?Collection $slots = null): array
    {
        $result = [];
        foreach ($slots ?? $reservation->slots()->whereNull('status')->get() as $slot) {
            foreach ($this->overlappingUndecidedSlots($reservation, $slot) as $other) {
                $result[$other->classroom_reservation_id] ??= ['reservation' => $other->reservation, 'slot' => $slot->label()];
            }
        }

        return array_values($result);
    }

    /** @return list<array{slot: ClassroomReservationSlot, class_name: string}> */
    private function conflictSlots(ClassroomReservation $reservation, Collection $slots): array
    {
        $conflicts = [];
        foreach ($slots as $slot) {
            $conflict = $this->approvedConflict(
                $reservation->classroom_id,
                $slot->reservation_date->toDateString(),
                $slot->start_time,
                $slot->end_time,
                $reservation->id
            );
            if ($conflict) {
                $conflicts[] = ['slot' => $slot, 'class_name' => $conflict->class_name];
            }
        }

        return $conflicts;
    }

    /** 其他待審核申請裡，和這個時段重疊、還沒審核的時段。 */
    private function overlappingUndecidedSlots(ClassroomReservation $reservation, ClassroomReservationSlot $slot): Collection
    {
        return ClassroomReservationSlot::query()
            ->whereNull('status')
            ->where('classroom_reservation_id', '<>', $reservation->id)
            ->whereDate('reservation_date', $slot->reservation_date->toDateString())
            ->where('start_time', '<', $slot->end_time)
            ->where('end_time', '>', $slot->start_time)
            ->whereHas('reservation', fn ($q) => $q->where('classroom_id', $reservation->classroom_id)->where('status', ClassroomReservation::STATUS_PENDING))
            ->with('reservation')
            ->get();
    }

    /** 剛同意的時段：把重疊的其他待審核時段自動拒絕。回傳拒絕的時段數。 */
    private function rejectOverlapping(ClassroomReservation $approvedReservation, Collection $approvedSlots): int
    {
        $byReservation = [];
        foreach ($approvedSlots as $slot) {
            foreach ($this->overlappingUndecidedSlots($approvedReservation, $slot) as $other) {
                $byReservation[$other->classroom_reservation_id][] = ['slot' => $other, 'class_name' => $approvedReservation->class_name];
            }
        }

        $count = 0;
        foreach ($byReservation as $reservationId => $conflicts) {
            $other = ClassroomReservation::query()->whereKey($reservationId)->lockForUpdate()->first();
            if ($other && ! $other->isReviewed()) {
                $count += $this->rejectConflictingSlots($other, $conflicts);
            }
        }

        return $count;
    }

    /**
     * 把和已核准時段重疊的時段拒絕；整筆制的申請整筆拒絕。回傳拒絕的時段數。
     *
     * @param  list<array{slot: ClassroomReservationSlot, class_name: string}>  $conflicts
     */
    private function rejectConflictingSlots(ClassroomReservation $reservation, array $conflicts): int
    {
        if ($reservation->is_all_or_nothing) {
            $count = $reservation->slots()->whereNull('status')->count();
            $this->autoReject($reservation, $this->conflictReason(['slot' => $conflicts[0]['slot']->label(), 'class_name' => $conflicts[0]['class_name']], true));

            return $count;
        }

        foreach ($conflicts as $conflict) {
            $conflict['slot']->update([
                'status' => ClassroomReservation::STATUS_REJECTED,
                'rejection_reason' => $this->conflictReason(['slot' => $conflict['slot']->label(), 'class_name' => $conflict['class_name']], false),
            ]);
        }
        $before = $reservation->status;
        $this->refreshStatus($reservation, null, null);

        AuditLogger::log('status_changed', $reservation, [
            'status' => ['old' => $before, 'new' => $reservation->status],
            'auto' => true,
            'rejected_slots' => array_map(fn ($c) => $c['slot']->label(), $conflicts),
        ], '系統自動拒絕重疊時段：' . $reservation->class_name);

        return count($conflicts);
    }

    /**
     * 依時段重新計算整筆申請的狀態：還有沒審核的時段＝待審核；全部同意＝已核准；全部拒絕＝已拒絕；其他＝部分核准。
     * $reviewerId 為 null 代表系統自動處理。
     */
    private function refreshStatus(ClassroomReservation $reservation, ?int $reviewerId, ?string $reason): void
    {
        $statuses = $reservation->slots()->pluck('status');

        if ($statuses->contains(null)) {
            $reservation->update(['status' => ClassroomReservation::STATUS_PENDING]);

            return;
        }

        $status = match (true) {
            $statuses->every(fn ($s) => $s === ClassroomReservation::STATUS_APPROVED) => ClassroomReservation::STATUS_APPROVED,
            $statuses->every(fn ($s) => $s === ClassroomReservation::STATUS_REJECTED) => ClassroomReservation::STATUS_REJECTED,
            default => ClassroomReservation::STATUS_PARTIALLY_APPROVED,
        };

        $reservation->update([
            'status' => $status,
            'reviewed_by' => $reviewerId ?? $reservation->reviewed_by,
            'reviewed_at' => now(),
            'rejection_reason' => $status === ClassroomReservation::STATUS_REJECTED
                ? ($reason ?? $reservation->slots()->whereNotNull('rejection_reason')->value('rejection_reason'))
                : null,
        ]);
    }

    /** @param array{slot: string, class_name: string} $conflict */
    private function conflictReason(array $conflict, bool $whole): string
    {
        return '因為' . $conflict['slot'] . ' 已核准給' . $conflict['class_name'] . '，' . ($whole ? '整筆申請自動拒絕（申請時選了其中一節無法借用就整筆不要）。' : '這一節自動拒絕。');
    }

    /** 系統整筆自動拒絕（整筆制的申請有一節被別人借走），並寫入操作紀錄。 */
    private function autoReject(ClassroomReservation $reservation, string $reason): void
    {
        ClassroomReservationSlot::query()->where('classroom_reservation_id', $reservation->id)->whereNull('status')
            ->update(['status' => ClassroomReservation::STATUS_REJECTED, 'rejection_reason' => $reason]);
        $before = $reservation->status;
        $this->refreshStatus($reservation, null, $reason);

        AuditLogger::log('status_changed', $reservation, [
            'status' => ['old' => $before, 'new' => $reservation->status],
            'auto' => true,
            'rejection_reason' => $reason,
        ], '系統自動拒絕空間預約：' . $reservation->class_name);
    }
}
