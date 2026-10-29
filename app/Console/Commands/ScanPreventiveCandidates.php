<?php

namespace App\Console\Commands;

use App\Services\AI\PreventiveCandidateService;
use Illuminate\Console\Command;

/**
 * 第 4 週任務 2：AI 預防保養掃描。
 * 手動執行：php artisan ai:scan-preventive
 * 排程：routes/console.php 每天自動執行一次。
 */
class ScanPreventiveCandidates extends Command
{
    protected $signature = 'ai:scan-preventive';

    protected $description = '掃描所有正常設備的保養歷史，風險分數達門檻者產生預防保養候選（或依設定直接建立 AI 工單）';

    public function handle(PreventiveCandidateService $service): int
    {
        $summary = $service->scan();

        $this->table(
            ['設備', '風險分數', '結果'],
            array_map(fn (array $row) => [
                $row['device_code'],
                $row['risk_score'] === null ? '—' : number_format($row['risk_score'], 3),
                $row['outcome'],
            ], $summary['details'])
        );

        $this->info(sprintf(
            '掃描 %d 台：新候選 %d、自動建單 %d、資料不足 %d、未達門檻 %d、重複略過 %d。',
            $summary['scanned'],
            $summary['candidates_created'],
            $summary['orders_created'],
            $summary['skipped_insufficient_data'],
            $summary['skipped_below_threshold'],
            $summary['skipped_duplicate'],
        ));

        return self::SUCCESS;
    }
}
