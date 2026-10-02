<?php

namespace App\Services\AI;

use App\Models\Device;

/**
 * PredictionServiceInterface 的佔位實作（第 3 週任務 5）。
 *
 * 目的只是證明「特徵抽取 → 預測服務」這條資料管線是通的，risk_score 直接拿
 * ng_rate（歷史 NG 比例）當替身，不是任何正式的風險評分演算法，
 * is_placeholder 固定回傳 true，呼叫端看到這個欄位就知道不能拿來當真的風險分數用。
 * 第 4 週會用一個新的 class（例如 RiskScorePredictionService）取代這個，
 * 到時候把這個 class 換掉或刪掉即可，介面不用動。
 */
class PlaceholderPredictionService implements PredictionServiceInterface
{
    public function __construct(private readonly MaintenanceFeatureExtractor $featureExtractor) {}

    public function predict(Device $device): array
    {
        $features = $this->featureExtractor->forDevice($device);

        return [
            'device_id' => $device->id,
            'risk_score' => $features['ng_rate'],
            'recommended_action' => $features['ng_rate'] !== null && $features['ng_rate'] > 0.5
                ? '歷史 NG 比例偏高，建議人工複查（僅為佔位邏輯，非正式規則）'
                : null,
            'features' => $features,
            'is_placeholder' => true,
        ];
    }
}
