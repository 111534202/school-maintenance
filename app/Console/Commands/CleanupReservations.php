<?php

namespace App\Console\Commands;

use App\Services\ReservationReviewService;
use Illuminate\Console\Command;

/**
 * 清除「已拒絕」的空間申請，以及時段全部已經開始、還沒審核完的申請（部分核准、已核准的保留）。
 * 排程：每月第二、四個週日跨到週一的 0 點（routes/console.php）；也可以手動執行 php artisan reservations:cleanup。
 */
class CleanupReservations extends Command
{
    protected $signature = 'reservations:cleanup';

    protected $description = '清除已拒絕與逾期未審核的空間申請';

    public function handle(ReservationReviewService $service): int
    {
        $count = $service->cleanup();
        $this->info("已清除 {$count} 筆空間申請。");

        return self::SUCCESS;
    }
}
