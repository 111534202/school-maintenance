<?php

namespace App\Services\AI;

use App\Models\Device;

/**
 * AI 預測服務介面（第 3 週任務 5）。
 *
 * 本週只定義這個介面與回傳資料格式，刻意不綁定任何實際演算法或模型——
 * 《個人工作計畫》的範圍控制明確寫「本週不把暫定 AI 公式宣稱為最終規格」。
 * 第 4 週要做風險評分/預防工單候選時，寫一個新的 class 實作這個介面即可，
 * 呼叫端（之後的 Dashboard、工單自動建立邏輯等）不用跟著改。
 */
interface PredictionServiceInterface
{
    /**
     * @return array{
     *     device_id: int,
     *     risk_score: float|null,
     *     recommended_action: string|null,
     *     features: array<string, mixed>,
     *     is_placeholder: bool,
     * }
     */
    public function predict(Device $device): array;
}
