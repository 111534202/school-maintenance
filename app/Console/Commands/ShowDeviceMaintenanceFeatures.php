<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\AI\PlaceholderPredictionService;
use Illuminate\Console\Command;

/**
 * 第 3 週任務 5 的完成證據：可以輸出某設備的歷史統計特徵。
 * 手動執行：php artisan ai:device-features DEV-0001
 */
class ShowDeviceMaintenanceFeatures extends Command
{
    protected $signature = 'ai:device-features {device_code : 設備編號，例如 DEV-0001}';

    protected $description = '輸出指定設備的保養歷史統計特徵，以及佔位預測服務的結果（驗證用，非正式 AI 輸出）';

    public function handle(PlaceholderPredictionService $predictionService): int
    {
        $device = Device::where('device_code', $this->argument('device_code'))->first();

        if (! $device) {
            $this->error("找不到設備編號「{$this->argument('device_code')}」。");

            return self::FAILURE;
        }

        $prediction = $predictionService->predict($device);

        $this->info("設備：{$device->device_code}（{$device->brand} {$device->model}）");
        $this->line(json_encode($prediction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
