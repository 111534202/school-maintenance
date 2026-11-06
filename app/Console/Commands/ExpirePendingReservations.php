<?php

namespace App\Console\Commands;

use App\Services\ReservationReviewService;
use Illuminate\Console\Command;

/**
 * 空間預約：最早的時段已經開始卻還沒審核的申請，整筆自動拒絕。
 * 手動執行：php artisan reservations:expire-pending
 * 排程：routes/console.php 每分鐘執行一次（預約列表、詳細頁、主控台打開時也會先檢查一次）。
 */
class ExpirePendingReservations extends Command
{
    protected $signature = 'reservations:expire-pending';

    protected $description = '把時段已經開始仍未審核、或和已核准時段重疊的空間預約申請整筆自動拒絕';

    public function handle(ReservationReviewService $service): int
    {
        $count = $service->sweep();

        $this->info("已自動拒絕 {$count} 筆空間預約申請（時間已過仍未審核，或和已核准的時段重疊）。");

        return self::SUCCESS;
    }
}
