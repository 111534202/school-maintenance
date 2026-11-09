<?php

// 這裡放「自訂的終端機指令」（php artisan xxx）與排程（Schedule）。
// 範例指令 inspire 是 Laravel 預設附的；保養/AI 模組（王佑恩）的排程在檔案下方。

use App\Console\Commands\ExpirePendingReservations;
use App\Console\Commands\GenerateDueMaintenanceOrders;
use App\Console\Commands\ScanPreventiveCandidates;
use Illuminate\Foundation\Inspiring;          // 產生勵志名言的內建工具
use Illuminate\Support\Facades\Artisan;       // 定義終端機指令用
use Illuminate\Support\Facades\Schedule;      // 排程用

// 範例：執行 `php artisan inspire` 會在終端機印出一句名言。
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

/**
 * 第 4 週任務 2：每天掃描一次設備風險，產生 AI 預防保養候選。
 * 掃描本身有去重（已有待審候選／即將到期工單就不重提），重複執行不會疊加。
 */
Schedule::command(ScanPreventiveCandidates::class)
    ->dailyAt('02:00')
    ->name('ai:scan-preventive')
    ->withoutOverlapping()
    ->onOneServer();

/**
 * 空間預約：時段已經開始還沒審核的申請，整筆自動拒絕（劉家芸）。
 * 每分鐘檢查一次；就算沒開排程，預約列表、詳細頁與主控台打開時也會先檢查。
 */
Schedule::command(ExpirePendingReservations::class)
    ->everyMinute()
    ->name('reservations:expire-pending')
    ->withoutOverlapping()
    ->onOneServer();

/**
 * 資料備份：每週日凌晨 2 點備份整個資料庫，並刪除超過保留天數的舊備份（保留天數在「資料備份」頁面設定）。
 * 跟上面的排程一樣，正式環境要設定每分鐘執行一次 `php artisan schedule:run`。
 */
Schedule::command(\App\Console\Commands\RunDatabaseBackup::class, ['--reason=scheduled'])
    ->weeklyOn(0, '02:00')
    ->name('backup:run')
    ->withoutOverlapping()
    ->onOneServer();
