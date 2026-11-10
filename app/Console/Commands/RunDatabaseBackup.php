<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * 備份資料庫並刪除超過保留天數的舊備份。
 * 排程：每月 1 號凌晨 0 點（routes/console.php）；也可以手動執行 php artisan backup:run。
 */
class RunDatabaseBackup extends Command
{
    protected $signature = 'backup:run {--reason=scheduled : scheduled（定期）或 manual（手動）}';

    protected $description = '備份資料庫（資料備份頁面看得到），並刪除超過保留天數的舊備份';

    public function handle(DatabaseBackup $backup): int
    {
        $name = $backup->create((string) $this->option('reason'));
        $deleted = $backup->prune();
        $this->info("已備份：{$name}" . ($deleted ? "；刪除 {$deleted} 份過期備份" : ''));

        return self::SUCCESS;
    }
}
