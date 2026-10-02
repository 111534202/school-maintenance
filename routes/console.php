<?php

use App\Console\Commands\GenerateDueMaintenanceOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 第 3 週任務 1：把 Week2 的定期工單產生器接上 Laravel Scheduler。
 *
 * 正式環境需要在伺服器上設定一條系統排程（Linux cron 或 Windows 工作排程器）
 * 每分鐘執行一次 `php artisan schedule:run`，Laravel 才會依下面設定的頻率
 * 實際觸發指令；本機開發沒有系統排程時，可以用
 * `php artisan schedule:work`（前景執行、模擬每分鐘 tick一次）來測試。
 *
 * 頻率訂為每天一次即可（保養到期日以「天」為單位），
 * withoutOverlapping() 避免萬一單次執行時間拉長，被下一次排程重疊觸發。
 * 「同一計畫同一到期日不重複產生」的防呆邏輯本身在 DueMaintenanceOrderGenerator
 * 裡（Week2 已做），這裡重複執行 schedule:run 不會疊加產生工單。
 */
Schedule::command(GenerateDueMaintenanceOrders::class)
    ->daily()
    ->name('maintenance:generate-due-orders')
    ->withoutOverlapping()
    ->onOneServer();
