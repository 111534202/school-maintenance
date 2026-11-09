<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Services\PartStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 一次性整理舊資料：把每個零件的使用、報廢依「日期與時間」重新扣一次批次
 * （以前是依登記先後扣，補登較早的紀錄會扣錯批次、算錯單價）。
 *
 * 用法：php artisan parts:rebuild-fifo           實際更新
 *       php artisan parts:rebuild-fifo --dry-run 只列出會被略過的零件，不改資料
 *
 * 依日期重新扣後，如果有某一筆在它的日期當時庫存不足，那個零件就不更新（維持原樣），並列出來請人工確認。
 */
class RebuildPartFifo extends Command
{
    protected $signature = 'parts:rebuild-fifo {--dry-run : 只檢查，不更新資料}';

    protected $description = '依日期與時間重新計算所有零件的先進先出（單價、小計、報廢損失、批次剩餘數量）';

    public function handle(PartStockService $stock): int
    {
        $updated = 0;
        $skipped = [];

        foreach (Part::query()->orderBy('id')->get() as $part) {
            DB::beginTransaction();
            try {
                $shortages = $stock->rebuild($part, strict: false);
                if ($shortages !== []) {
                    DB::rollBack();
                    $first = $shortages[0];
                    $skipped[] = [$part->part_code, $part->name, $first['at']->format('Y-m-d H:i') . ' 的' . $first['label']];
                    continue;
                }
                $this->option('dry-run') ? DB::rollBack() : DB::commit();
                $updated++;
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        $this->info(($this->option('dry-run') ? '（只檢查）可更新 ' : '已重新計算 ') . $updated . ' 個零件。');
        if ($skipped !== []) {
            $this->warn('下列零件依日期重新扣後會有紀錄當時庫存不足，維持原樣，請人工確認日期或數量：');
            $this->table(['零件編號', '名稱', '第一筆不足的紀錄'], $skipped);
        }

        return self::SUCCESS;
    }
}
