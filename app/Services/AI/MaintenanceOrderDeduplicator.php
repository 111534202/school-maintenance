<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\PreventiveCandidate;
use Illuminate\Support\Carbon;

/**
 * AI／定期工單去重（第 4 週任務 4）。
 *
 * 同一台設備符合下列任一條件，就「不要再提出／建立」AI 預防保養：
 *   1. open_order        已有未完成（待處理／進行中）的保養工單，且排定日在「今天 + 去重天數」以內
 *                        （含已逾期或沒排日期的）——定期保養馬上就要做了，不需要 AI 再重複開一張。
 *   2. pending_candidate 已有一筆待審核的候選（掃描重複執行不會疊加）。
 *   3. recent_ai_order   最近「去重天數」內已經建立過 AI 來源的工單。
 *   4. recently_rejected 最近「去重天數」內剛被主管駁回過同一台設備的候選（避免天天重提）。
 *
 * 工程實作決定，待全組確認：第 1 週鎖定的正式去重規則我這邊沒有拿到，先用上面這組；
 * 「去重天數」可在 AI 設定頁調整（預設 14 天）。
 * 掃描時與主管核准時都會呼叫，核准時用 $ignoreCandidate 排除自己這筆候選。
 */
class MaintenanceOrderDeduplicator
{
    public const REASON_OPEN_ORDER = 'open_order';

    public const REASON_PENDING_CANDIDATE = 'pending_candidate';

    public const REASON_RECENT_AI_ORDER = 'recent_ai_order';

    public const REASON_RECENTLY_REJECTED = 'recently_rejected';

    /**
     * @return string|null 重複原因代碼；null 代表沒有重複、可以建立
     */
    public function findDuplicateReason(Device $device, ?PreventiveCandidate $ignoreCandidate = null): ?string
    {
        $windowDays = (int) AiSetting::get(AiSetting::DEDUP_WINDOW_DAYS);
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($windowDays);
        $since = Carbon::now()->subDays($windowDays);

        $hasOpenOrder = MaintenanceOrder::query()
            ->where('device_id', $device->id)
            ->whereIn('status', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->where(function ($query) use ($horizon) {
                $query->whereNull('scheduled_date')
                    ->orWhereDate('scheduled_date', '<=', $horizon->toDateString());
            })
            ->exists();

        if ($hasOpenOrder) {
            return self::REASON_OPEN_ORDER;
        }

        $hasPendingCandidate = PreventiveCandidate::query()
            ->where('device_id', $device->id)
            ->where('status', PreventiveCandidate::STATUS_PENDING)
            ->when($ignoreCandidate, fn ($query) => $query->where('id', '!=', $ignoreCandidate->id))
            ->exists();

        if ($hasPendingCandidate) {
            return self::REASON_PENDING_CANDIDATE;
        }

        $hasRecentAiOrder = MaintenanceOrder::query()
            ->where('device_id', $device->id)
            ->where('source', MaintenanceOrder::SOURCE_AI)
            ->where('created_at', '>=', $since)
            ->exists();

        if ($hasRecentAiOrder) {
            return self::REASON_RECENT_AI_ORDER;
        }

        $recentlyRejected = PreventiveCandidate::query()
            ->where('device_id', $device->id)
            ->where('status', PreventiveCandidate::STATUS_REJECTED)
            ->where('decided_at', '>=', $since)
            ->when($ignoreCandidate, fn ($query) => $query->where('id', '!=', $ignoreCandidate->id))
            ->exists();

        return $recentlyRejected ? self::REASON_RECENTLY_REJECTED : null;
    }

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_OPEN_ORDER => '已有即將到期／進行中的保養工單',
            self::REASON_PENDING_CANDIDATE => '已有待審核的候選',
            self::REASON_RECENT_AI_ORDER => '近期已建立過 AI 保養工單',
            self::REASON_RECENTLY_REJECTED => '近期剛被駁回過',
            default => $reason,
        };
    }
}
