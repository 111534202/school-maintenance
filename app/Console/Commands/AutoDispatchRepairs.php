<?php

namespace App\Console\Commands;

use App\Services\AutoRepairDispatcher;
use Illuminate\Console\Command;

/**
 * 把還在等待的「新報修」自動派給已經閒下來的維修人員：php artisan repairs:auto-dispatch
 * 排程每分鐘會執行一次（routes/console.php）；送出維修紀錄時也會立刻補派，這裡是保險
 * （案件被取消、重新指派、新增維修人員…也能補派）。功能關閉（REPAIRS_AUTO_DISPATCH=false）時什麼都不做。
 */
class AutoDispatchRepairs extends Command
{
    protected $signature = 'repairs:auto-dispatch';

    protected $description = '把等待中的新報修自動派給目前沒有進行中工單的維修人員';

    public function handle(AutoRepairDispatcher $dispatcher): int
    {
        if (! AutoRepairDispatcher::enabled()) {
            $this->line('報修自動派工已關閉（REPAIRS_AUTO_DISPATCH=false），略過。');

            return self::SUCCESS;
        }

        $this->info('已自動派工 ' . $dispatcher->dispatchWaiting() . ' 張報修單。');

        return self::SUCCESS;
    }
}
