<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RepairDurationStatisticsService
{
    /**
     * 計算指定期間內已結案報修單的平均累計維修工時。
     *
     * 判定資格使用 repair_logs.ended_at；
     * 符合資格後，加總該報修單所有歷史維修紀錄。
     *
     * 若任何一筆維修紀錄沒有有效工時，整張報修單不納入平均，
     * 但 missing_hours_request_count 仍只計算一次。
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     average_hours: float|null,
     *     qualifying_request_count: int,
     *     included_request_count: int,
     *     missing_hours_request_count: int
     * }
     */
    public function forPeriod(
        User $user,
        Carbon $from,
        Carbon $to
    ): array {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        if ($start->gt($end)) {
            return [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'average_hours' => null,
                'qualifying_request_count' => 0,
                'included_request_count' => 0,
                'missing_hours_request_count' => 0,
            ];
        }

        $requests = RepairRequest::query()
            ->visibleTo($user)
            ->where('status', RepairRequestStatus::Completed->value)
            ->whereHas('repairLogs', function ($query) use ($start, $end) {
                $query->whereNotNull('ended_at')
                    ->where('ended_at', '>=', $start)
                    ->where('ended_at', '<=', $end);
            })
            // 取得該報修單全部歷史紀錄，不只期間內的紀錄。
            ->with('repairLogs')
            ->get();

        $includedHours = [];
        $missingCount = 0;

        foreach ($requests as $request) {
            $totalHours = 0.0;
            $hasMissingHours = false;

            foreach ($request->repairLogs as $log) {
                if ($log->total_hours !== null) {
                    $storedHours = (float) $log->total_hours;

                    if (! is_finite($storedHours) || $storedHours < 0) {
                        $hasMissingHours = true;
                        break;
                    }

                    // 儲存值優先；即使為 0，也不改用起訖時間。
                    $totalHours += $storedHours;

                    continue;
                }

                if ($log->started_at === null || $log->ended_at === null) {
                    $hasMissingHours = true;
                    break;
                }

                $startedAt = $log->started_at;
                $endedAt = $log->ended_at;

                $seconds = $endedAt->getTimestamp()
                    - $startedAt->getTimestamp();

                if ($seconds < 0) {
                    $hasMissingHours = true;
                    break;
                }

                $totalHours += $seconds / 3600;
            }

            if ($hasMissingHours) {
                $missingCount++;

                continue;
            }

            $includedHours[] = $totalHours;
        }

        $includedCount = count($includedHours);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'average_hours' => $includedCount > 0
                ? round(array_sum($includedHours) / $includedCount, 2)
                : null,
            'qualifying_request_count' => $requests->count(),
            'included_request_count' => $includedCount,
            'missing_hours_request_count' => $missingCount,
        ];
    }
}