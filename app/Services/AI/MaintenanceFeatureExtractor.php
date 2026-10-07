<?php

namespace App\Services\AI;

use App\Models\Device;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * AI 資料準備（第 3 週任務 5）。
 *
 * 把 maintenance_orders / maintenance_results 整理成可以餵給之後 AI 預測用的統計特徵，
 * 本週只做「資料準備」，不做任何預測演算法，也不決定最終要用哪些特徵、哪個模型。
 *
 * repair_logs 特徵（報修/維修歷史）：彭仕衡的報修模組分支尚未併入 develop，
 * 目前資料庫裡根本沒有 repair_logs 這張表，所以這裡的報修相關欄位先全部回傳 null，
 * 並在陣列裡用 repair_data_available=false 明確標記「這些欄位目前沒有真實資料」，
 * 不要讓呼叫端誤以為 null 就是「這台設備沒修過」。等他的分支併進來之後，
 * 再回來改成真的查詢 repair_logs，不會另外建一張重複的表。
 */
class MaintenanceFeatureExtractor
{
    /**
     * 單一設備的歷史統計特徵。
     *
     * @return array<string, mixed>
     */
    public function forDevice(Device $device): array
    {
        $orders = MaintenanceOrder::query()
            ->where('device_id', $device->id)
            ->with('result')
            ->orderBy('scheduled_date')
            ->get();

        return $this->summarize($device->id, $device->device_code, $orders);
    }

    /**
     * 同廠牌/型號的所有設備彙總在一起的統計特徵（Task 2 設備履歷頁是單台設備，
     * 這裡是給 AI 用的「這個型號整體表現」視角，例如同型號設備是不是特別常壞）。
     *
     * @return array<string, mixed>
     */
    public function forModel(string $brand, string $model): array
    {
        $deviceIds = Device::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->pluck('id');

        $orders = MaintenanceOrder::query()
            ->whereIn('device_id', $deviceIds)
            ->with('result')
            ->orderBy('scheduled_date')
            ->get();

        $features = $this->summarize(null, null, $orders);
        $features['brand'] = $brand;
        $features['model'] = $model;
        $features['device_count'] = $deviceIds->count();

        return $features;
    }

    /**
     * @param  Collection<int, MaintenanceOrder>  $orders
     * @return array<string, mixed>
     */
    private function summarize(?int $deviceId, ?string $deviceCode, Collection $orders): array
    {
        $completed = $orders->where('status', MaintenanceOrder::STATUS_COMPLETED);
        $resultsWithData = $completed->map(fn (MaintenanceOrder $order) => $order->result)->filter();

        $ngCount = $resultsWithData->filter(fn ($result) => $result->isNg())->count();
        $okCount = $resultsWithData->count() - $ngCount;

        $lastResult = $resultsWithData->sortByDesc(fn ($result) => $result->executed_at)->first();

        // 第 4 週：依執行時間由舊到新排列的 OK/NG 序列，給規則式風險評分算「最近 N 筆 NG 比例」
        // 與「結尾連續 NG 次數」用。同一筆資料排序結果固定，確保評分可重現。
        $resultSequence = $resultsWithData
            ->sortBy(fn ($result) => $result->executed_at)
            ->map(fn ($result) => $result->isNg() ? 'ng' : 'ok')
            ->values()
            ->all();

        return [
            'device_id' => $deviceId,
            'device_code' => $deviceCode,
            'total_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
            'ng_rate' => $resultsWithData->count() > 0
                ? round($ngCount / $resultsWithData->count(), 4)
                : null,
            'result_sequence' => $resultSequence,
            'last_maintenance_at' => $lastResult?->executed_at?->toDateTimeString(),
            // 工程實作修正：Carbon 3 的 diffInDays() 預設改成回傳「有方向性」的 float
            // （過去的日期會是負數、還會帶一堆小數），不再是舊版 Carbon 那種無條件正數整數天。
            // 這裡要的是「距上次保養過了幾天」，所以明確帶 absolute: true 並四捨五入成整數天。
            'days_since_last_maintenance' => $lastResult?->executed_at
                ? (int) round(Carbon::now()->diffInDays($lastResult->executed_at, absolute: true))
                : null,

            // 報修/維修特徵：彭仕衡的 repair_logs 分支尚未併入 develop，先佔位。
            'repair_data_available' => false,
            'repair_count' => null,
            'avg_repair_interval_days' => null,
        ];
    }
}
