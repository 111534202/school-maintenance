<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\ClassroomReservationSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 教室預約的審核與時段衝突檢查（送出申請、整筆核准／拒絕、自動拒絕都用這裡）。
 *
 * 一筆申請只能整筆核准或整筆拒絕，不能只同意其中幾節：
 * - 核准 B 時，和 B 任一時段重疊的其他待審核申請（例如 A 申請週一、週二第 1 節，B 申請週二第 1 節）整筆自動拒絕。
 * - 最早的時段已經開始卻還沒審核的申請，整筆自動拒絕。
 * - 和已核准時段重疊的待審核申請（例如改版前就核准的資料），整筆自動拒絕。
 * 舊資料可能有 partially_approved（以前可以分時段審核），畫面仍會照時段各自的結果顯示。
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

    /**
     * 整筆核准：每個時段都同意。只要有一個時段和其他已核准的時段重疊，就整筆不能核准（只能拒絕）。
     * 核准成功後，同一間教室裡和這筆申請任一時段重疊的「待審核」申請，會整筆自動拒絕。
     *
     * 回傳 ['type' => reviewed|already_reviewed|expired|unavailable|conflict, ...]：
     * - reviewed 會附上 'auto_rejected'（被自動拒絕的申請數）。
     * - conflict：有時段已核准給別人，這筆已整筆自動拒絕；附上 'slots'（衝突的時段文字）。
     */
    public function approveAll(ClassroomReservation $reservation, User $reviewer): array
    {
        return DB::transaction(function () use ($reservation, $reviewer) {
            // 鎖定申請，避免兩位審核人員同時審核。
            $locked = ClassroomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReviewed()) {
                return ['type' => 'already_reviewed'];
            }

            $slots = $locked->slots()->get();

            // 最早的時段已經開始：不能再核准，直接自動拒絕。
            if ($this->hasStarted($slots->first())) {
                $this->autoReject($locked, $this->expiredReason($slots->first()));

                return ['type' => 'expired'];
            }

            // 教室必須仍然啟用、沒有暫停預約（設備異常或核心設備等待維修）。
            $classroom = Classroom::query()->whereKey($locked->classroom_id)->lockForUpdate()->first();
            if (! $classroom || ! $classroom->is_active || ClassroomRepairNotices::isSuspended($classroom->id)) {
                return ['type' => 'unavailable'];
            }

            // 有時段已經核准給別人（例如改版前就核准的資料）：不用再操作，整筆自動拒絕。
            $conflicts = $this->conflictLabels($locked, $slots);
            if ($conflicts) {
                $this->autoReject($locked, $this->conflictReason($conflicts[0]));

                return ['type' => 'conflict', 'slots' => array_column($conflicts, 'text')];
            }

            foreach ($slots as $slot) {
                $slot->update(['status' => ClassroomReservation::STATUS_APPROVED, 'rejection_reason' => null]);
            }

            $locked->update([
                'status' => ClassroomReservation::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLogger::log('status_changed', $locked, [
                'status' => ['old' => ClassroomReservation::STATUS_PENDING, 'new' => ClassroomReservation::STATUS_APPROVED],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $locked->reviewed_at->toDateTimeString(),
            ], '核准空間預約：' . $locked->class_name);

            // 和這筆重疊的其他待審核申請：整筆自動拒絕（不能只留下沒重疊的時段）。
            $autoRejected = 0;
            foreach ($this->overlappingPending($locked, $slots) as $other) {
                $this->autoReject($other['reservation'], $this->conflictReason(['slot' => $other['slot'], 'class_name' => $locked->class_name]));
                $autoRejected++;
            }

            return ['type' => 'reviewed', 'status' => ClassroomReservation::STATUS_APPROVED, 'auto_rejected' => $autoRejected];
        });
    }

    /** 整筆拒絕：每個時段都拒絕，原因相同（原因可以不填）。 */
    public function rejectAll(ClassroomReservation $reservation, User $reviewer, ?string $reason = null): array
    {
        return DB::transaction(function () use ($reservation, $reviewer, $reason) {
            $locked = ClassroomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReviewed()) {
                return ['type' => 'already_reviewed'];
            }

            $this->rejectWhole($locked, filled($reason) ? trim($reason) : null, $reviewer->id);

            AuditLogger::log('status_changed', $locked, [
                'status' => ['old' => ClassroomReservation::STATUS_PENDING, 'new' => ClassroomReservation::STATUS_REJECTED],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $locked->reviewed_at->toDateTimeString(),
                'rejection_reason' => $locked->rejection_reason,
            ], '拒絕空間預約：' . $locked->class_name);

            return ['type' => 'reviewed', 'status' => ClassroomReservation::STATUS_REJECTED];
        });
    }

    /**
     * 時間已經到了還沒審核的申請：只要最早的時段已經開始（例如週一第 1 節已經上課），整筆自動拒絕。
     * 由 sweep() 呼叫。回傳這次自動拒絕的筆數。
     */
    public function expireOverdue(): int
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        $ids = ClassroomReservation::query()
            ->where('status', ClassroomReservation::STATUS_PENDING)
            ->whereHas('slots', fn ($q) => $q->where(fn ($q) => $q->whereDate('reservation_date', '<', $today)
                ->orWhere(fn ($q) => $q->whereDate('reservation_date', $today)->where('start_time', '<=', $time))))
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id) {
                $locked = ClassroomReservation::query()->whereKey($id)->lockForUpdate()->first();
                if (! $locked || $locked->isReviewed()) {
                    return 0;
                }
                $this->autoReject($locked, $this->expiredReason($locked->slots()->first()));

                return 1;
            });
        }

        return $count;
    }

    /**
     * 自動拒絕的總整理：時間已到還沒審核的、以及和已核准時段重疊的待審核申請，都整筆自動拒絕。
     * 排程每分鐘執行（reservations:expire-pending）；空間申請列表、預約詳細與主控台打開時也會先跑一次。
     * 回傳這次自動拒絕的筆數。
     */
    public function sweep(): int
    {
        return $this->expireOverdue() + $this->rejectConflictingWithApproved();
    }

    /**
     * 待審核申請裡，只要有一個時段和已核准的時段重疊（例如改版前就核准的資料），就整筆自動拒絕，不用再手動操作。
     */
    public function rejectConflictingWithApproved(): int
    {
        $count = 0;
        $pending = ClassroomReservation::query()->where('status', ClassroomReservation::STATUS_PENDING)->pluck('id');

        foreach ($pending as $id) {
            $count += DB::transaction(function () use ($id) {
                $locked = ClassroomReservation::query()->whereKey($id)->lockForUpdate()->first();
                if (! $locked || $locked->isReviewed()) {
                    return 0;
                }
                $conflicts = $this->conflictLabels($locked, $locked->slots()->get());
                if (! $conflicts) {
                    return 0;
                }
                $this->autoReject($locked, $this->conflictReason($conflicts[0]));

                return 1;
            });
        }

        return $count;
    }

    /**
     * 這筆申請裡和其他已核准時段重疊的時段（有的話整筆會自動拒絕）。
     *
     * @return list<array{slot: string, class_name: string, text: string}>
     */
    public function conflictLabels(ClassroomReservation $reservation, ?Collection $slots = null): array
    {
        $conflicts = [];
        foreach ($slots ?? $reservation->slots as $slot) {
            $conflict = $this->approvedConflict(
                $reservation->classroom_id,
                $slot->reservation_date->toDateString(),
                $slot->start_time,
                $slot->end_time,
                $reservation->id
            );
            if ($conflict) {
                $conflicts[] = [
                    'slot' => $slot->label(),
                    'class_name' => $conflict->class_name,
                    'text' => $slot->label() . '（已核准給 ' . $conflict->class_name . '）',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * 同一間教室裡，和這筆申請任一時段重疊的其他「待審核」申請（核准這筆後會被自動拒絕）。
     *
     * @return list<array{reservation: ClassroomReservation, slot: string}>
     */
    public function overlappingPending(ClassroomReservation $reservation, ?Collection $slots = null): array
    {
        $result = [];
        foreach ($slots ?? $reservation->slots as $slot) {
            $others = ClassroomReservation::query()
                ->where('classroom_id', $reservation->classroom_id)
                ->where('status', ClassroomReservation::STATUS_PENDING)
                ->whereKeyNot($reservation->id)
                ->whereHas('slots', fn ($q) => $q->whereDate('reservation_date', $slot->reservation_date->toDateString())
                    ->where('start_time', '<', $slot->end_time)
                    ->where('end_time', '>', $slot->start_time))
                ->get();

            foreach ($others as $other) {
                $result[$other->id] ??= ['reservation' => $other, 'slot' => $slot->label()];
            }
        }

        return array_values($result);
    }

    /** 時段是否已經開始（開始時間已到或已過）。 */
    private function hasStarted(?ClassroomReservationSlot $slot): bool
    {
        if (! $slot) {
            return false;
        }

        return $slot->reservation_date->toDateString() . ' ' . substr((string) $slot->start_time, 0, 5) <= now()->format('Y-m-d H:i');
    }

    /** @param array{slot: string, class_name: string} $conflict */
    private function conflictReason(array $conflict): string
    {
        return '因為' . $conflict['slot'] . ' 已核准給' . $conflict['class_name'] . '，整筆申請自動拒絕。';
    }

    private function expiredReason(?ClassroomReservationSlot $slot): string
    {
        return '申請的時段' . ($slot ? '（' . $slot->label() . '）' : '') . '已經開始仍未審核，系統自動拒絕。';
    }

    /** 整筆拒絕：每個時段都標成拒絕、原因相同。$reviewerId 為 null 代表系統自動拒絕。 */
    private function rejectWhole(ClassroomReservation $reservation, ?string $reason, ?int $reviewerId): void
    {
        ClassroomReservationSlot::query()->where('classroom_reservation_id', $reservation->id)->update(['status' => ClassroomReservation::STATUS_REJECTED, 'rejection_reason' => $reason]);

        $reservation->update([
            'status' => ClassroomReservation::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /** 系統自動拒絕（時段重疊被別人核准、或時間已過仍未審核），並寫入操作紀錄。 */
    private function autoReject(ClassroomReservation $reservation, string $reason): void
    {
        $this->rejectWhole($reservation, $reason, null);

        AuditLogger::log('status_changed', $reservation, [
            'status' => ['old' => ClassroomReservation::STATUS_PENDING, 'new' => ClassroomReservation::STATUS_REJECTED],
            'auto' => true,
            'rejection_reason' => $reason,
        ], '系統自動拒絕空間預約：' . $reservation->class_name);
    }
}
