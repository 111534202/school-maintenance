<?php

namespace App\Console\Commands;

use App\Services\DueMaintenanceOrderGenerator;
use Illuminate\Console\Command;

/**
 * 第 2 週任務 4：定期工單產生器。
 * 手動執行：php artisan maintenance:generate-due-orders
 * 依規格「先支援手動執行 command 驗證，不要求排程 daemon」，本週不接 Laravel Scheduler。
 */
class GenerateDueMaintenanceOrders extends Command
{
    protected $signature = 'maintenance:generate-due-orders';

    protected $description = '依保養計畫的下次到期日，為已到期且啟用中的計畫產生保養工單（同一計畫同一到期日不重複產生）';

    public function handle(DueMaintenanceOrderGenerator $generator): int
    {
        $count = $generator->generate();

        $this->info("已產生 {$count} 筆到期保養工單。");

        return self::SUCCESS;
    }
}
