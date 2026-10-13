<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use App\Models\Device;

/**
 * 規則式（可解釋）設備風險評分（第 4 週任務 1）。
 *
 * 這不是機器學習：沒有訓練、沒有黑箱，風險分數 = 三個規則項目的加權和，
 * 每一項的原始值、權重、貢獻分數都會放進 explanation 回傳，畫面與測試都可以直接檢查。
 * 同一份保養歷史、同一天呼叫，結果一定相同（可重現）。
 *
 *   風險分數（0~1）= 0.5 × 最近 N 筆保養的 NG 比例
 *                  + 0.3 × min(結尾連續 NG 次數, 3) / 3
 *                  + 0.2 × 保養逾期程度（距上次保養 ≤45 天為 0，≥90 天為 1，中間線性）
 *
 * 工程實作決定，待全組確認：第 1 週鎖定的 AI 判斷規則我這邊沒有拿到正式版本，
 * 所以權重、45/90 天、連續 NG 上限 3 次都先用上面這組預設值。
 * 全部集中在本檔案的常數，要改規則只需要改這裡，不影響其他程式。
 *
 * 報修／維修歷史（repair_logs）尚未併入，目前不納入評分，並在 notes 明確標示；
 * 併入後再新增一個評分項目即可，不需要改介面。
 */
class RuleBasedPredictionService implements PredictionServiceInterface
{
    public const ALGORITHM = 'rule_based_v1';

    public const WEIGHT_NG_RATE = 0.5;

    public const WEIGHT_NG_STREAK = 0.3;

    public const WEIGHT_OVERDUE = 0.2;

    public const NG_STREAK_CAP = 3;

    public const OVERDUE_GRACE_DAYS = 45;

    public const OVERDUE_FULL_DAYS = 90;

    public function __construct(private readonly MaintenanceFeatureExtractor $featureExtractor) {}

    public function predict(Device $device): array
    {
        $features = $this->featureExtractor->forDevice($device);

        $threshold = (float) AiSetting::get(AiSetting::RISK_THRESHOLD);
        $minCompleted = (int) AiSetting::get(AiSetting::MIN_COMPLETED_ORDERS);
        $window = max(1, (int) AiSetting::get(AiSetting::RECENT_WINDOW));

        $notes = [];
        if (! $features['repair_data_available']) {
            $notes[] = '本次評分只依據保養紀錄，未納入報修／維修次數（報修紀錄請見設備履歷的「報修與維修紀錄」）。';
        }

        /** @var array<int, string> $sequence */
        $sequence = $features['result_sequence'];
        $resultCount = count($sequence);

        $base = [
            'device_id' => $device->id,
            'features' => $features,
            'is_placeholder' => false,
            'algorithm' => self::ALGORITHM,
            'threshold' => $threshold,
            'notes' => $notes,
        ];

        if ($resultCount < $minCompleted) {
            return $base + [
                'risk_score' => null,
                'recommended_action' => "保養歷史不足（{$resultCount}/{$minCompleted} 筆），暫不評分。",
                'insufficient_data' => true,
                'explanation' => [],
            ];
        }

        $recent = array_slice($sequence, -$window);
        $recentNg = count(array_filter($recent, fn (string $r) => $r === 'ng'));
        $ngRate = $recentNg / count($recent);

        $streak = 0;
        foreach (array_reverse($sequence) as $r) {
            if ($r !== 'ng') {
                break;
            }
            $streak++;
        }
        $streakRatio = min($streak, self::NG_STREAK_CAP) / self::NG_STREAK_CAP;

        $days = $features['days_since_last_maintenance'];
        $overdue = $days === null
            ? 0.0
            : min(1.0, max(0.0, ($days - self::OVERDUE_GRACE_DAYS) / (self::OVERDUE_FULL_DAYS - self::OVERDUE_GRACE_DAYS)));

        $explanation = [
            $this->component('recent_ng_rate', '最近 '.count($recent).' 筆保養的 NG 比例', $ngRate, self::WEIGHT_NG_RATE, "{$recentNg} / ".count($recent).' 筆為 NG'),
            $this->component('ng_streak', '結尾連續 NG 次數', $streakRatio, self::WEIGHT_NG_STREAK, "連續 {$streak} 次 NG（上限 ".self::NG_STREAK_CAP.' 次計滿分）'),
            $this->component('overdue', '保養逾期程度', $overdue, self::WEIGHT_OVERDUE, $days === null
                ? '沒有保養紀錄'
                : "距上次保養 {$days} 天（".self::OVERDUE_GRACE_DAYS.' 天內不扣分、'.self::OVERDUE_FULL_DAYS.' 天以上計滿分）'),
        ];

        $score = round(min(1.0, max(0.0, array_sum(array_column($explanation, 'contribution')))), 4);

        return $base + [
            'risk_score' => $score,
            'recommended_action' => $this->recommend($score, $threshold),
            'insufficient_data' => false,
            'explanation' => $explanation,
        ];
    }

    /**
     * @return array{key: string, label: string, raw: float, weight: float, contribution: float, detail: string}
     */
    private function component(string $key, string $label, float $raw, float $weight, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'raw' => round($raw, 4),
            'weight' => $weight,
            'contribution' => round($raw * $weight, 4),
            'detail' => $detail,
        ];
    }

    private function recommend(float $score, float $threshold): ?string
    {
        if ($score >= $threshold) {
            return '風險分數已達門檻，建議安排預防保養。';
        }

        if ($score >= $threshold * 0.7) {
            return '風險接近門檻，建議持續觀察。';
        }

        return null;
    }
}
