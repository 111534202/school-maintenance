<?php

namespace App\Services;

use App\Exceptions\PartStockTimelineException;
use App\Models\MaintenancePartUsage;
use App\Models\Part;
use App\Models\PartPurchase;
use App\Models\PartScrap;
use App\Models\PartScrapBatch;
use App\Models\RepairPartUsage;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 零件庫存的唯一進出口：進貨（採購紀錄）、用料（維修、保養）與報廢都要經過這裡，
 * 才能讓「零件的目前庫存」永遠等於「各採購批次的剩餘數量加總」。
 *
 * 成本計算採先進先出（FIFO）：用料時先扣最早買進、還有剩的批次。
 * 例：10/10 買 2 條（20 元）、10/12 買 2 條（30 元）
 *     10/15 用 1 條 → 20 元；10/17 用 2 條 → 20 元＋30 元＝50 元。
 *
 * 依日期重新計算（rebuild）：每次新增、修改、刪除採購、使用、報廢後，把這個零件所有的使用與報廢
 * 依「發生的日期與時間」從頭重新扣一次批次，而不是依登記的先後。所以補登一筆比較早的報廢，
 * 會扣到當時最早的批次，後面的使用自動往後順延，單價、小計、報廢損失、批次剩餘數量都跟著更新。
 * 每一筆只能扣「日期不晚於它」的採購；如果因此有某一筆在它的日期當時庫存不夠，整個動作會被擋下
 * （PartStockTimelineException），什麼都不會改。
 *
 * 所有方法都會鎖定零件資料（lockForUpdate），避免兩個人同時扣庫存時算錯；
 * 請在資料庫交易（DB::transaction）中呼叫，或讓方法自己開交易。
 */
class PartStockService
{
    /**
     * 新增採購：建立一個進貨批次，並把數量加進目前庫存。
     *
     * @param  array{purchase_date: string, vendor_name: ?string, quantity: int, unit_price: int, note?: ?string}  $data
     */
    public function receive(Part $part, array $data): PartPurchase
    {
        return DB::transaction(function () use ($part, $data) {
            $locked = Part::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();

            $purchase = $locked->purchases()->create([
                ...$data,
                'remaining_quantity' => $data['quantity'],
            ]);

            $locked->increment('current_stock', $data['quantity']);
            $this->rebuild($locked);   // 補登較早的採購時，之後的使用、報廢可能改扣這一批

            return $purchase->refresh();
        });
    }

    /**
     * 修改採購：日期、廠商、備註隨時可以改；數量與單價只有在這一批還沒被用過時才能改，
     * 改數量時同步調整目前庫存。
     *
     * @throws DomainException 這一批已經被用過，卻要改數量或單價
     */
    public function update(PartPurchase $purchase, array $data): PartPurchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $locked = PartPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            Part::query()->whereKey($locked->part_id)->lockForUpdate()->first();

            $changesAmount = (int) $data['quantity'] !== $locked->quantity || (int) $data['unit_price'] !== $locked->unit_price;
            if ($changesAmount && $locked->isUsed()) {
                throw new DomainException('這一批已經有 ' . $locked->usedQuantity() . ' 個被維修、保養用掉或報廢，不能再修改數量與單價（日期、廠商、備註仍可修改）。');
            }

            $difference = (int) $data['quantity'] - $locked->quantity;

            $locked->update([
                ...$data,
                'remaining_quantity' => $locked->remaining_quantity + $difference,
            ]);

            if ($difference !== 0) {
                Part::query()->whereKey($locked->part_id)->increment('current_stock', $difference);
            }
            $this->rebuild($locked->part);   // 改日期、數量、單價後重新依日期扣批次

            return $locked->refresh();
        });
    }

    /**
     * 刪除採購：只有還沒被用過的批次可以刪除，刪除時把數量從目前庫存扣回。
     *
     * @throws DomainException 這一批已經被用過
     */
    public function delete(PartPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $locked = PartPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            Part::query()->whereKey($locked->part_id)->lockForUpdate()->first();

            if ($locked->isUsed()) {
                throw new DomainException('這一批已經有 ' . $locked->usedQuantity() . ' 個被維修、保養用掉或報廢，不能刪除。');
            }

            Part::query()->whereKey($locked->part_id)->decrement('current_stock', $locked->quantity);
            $part = $locked->part;
            $locked->deleteAttachments();
            $locked->delete();
            $this->rebuild($part);
        });
    }

    /**
     * 用料：依先進先出從各批次扣除數量，並扣除目前庫存。
     * 回傳每一批扣了多少與當時的單價，呼叫端用它建立用料明細（一批一列）。
     *
     * 若舊資料的批次剩餘數量不足（理論上不會發生），不足的部分 unit_price 記為 null，
     * 成本報表會把它列為「缺少單價」，而不是當成 0 元。
     *
     * @return list<array{part_purchase_id: ?int, quantity: int, unit_price: ?int}>
     *
     * @throws DomainException 目前庫存不足
     */
    public function consume(Part $part, int $quantity): array
    {
        return DB::transaction(function () use ($part, $quantity) {
            $locked = Part::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();

            if ($quantity < 1) {
                throw new DomainException('零件使用數量必須至少為 1。');
            }

            if ($locked->current_stock < $quantity) {
                throw new DomainException("零件「{$locked->name}」庫存不足，目前庫存 {$locked->current_stock}，需要 {$quantity}。");
            }

            $allocations = [];
            $left = $quantity;

            $batches = PartPurchase::query()
                ->where('part_id', $locked->id)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($left === 0) {
                    break;
                }

                $take = min($left, $batch->remaining_quantity);
                $batch->decrement('remaining_quantity', $take);
                $allocations[] = ['part_purchase_id' => $batch->id, 'quantity' => $take, 'unit_price' => $batch->unit_price];
                $left -= $take;
            }

            if ($left > 0) {
                $allocations[] = ['part_purchase_id' => null, 'quantity' => $left, 'unit_price' => null];
            }

            $locked->decrement('current_stock', $quantity);

            return $allocations;
        });
    }

    /**
     * 報廢：跟用料一樣依先進先出從各批次扣除，並記下扣了哪幾批（報廢損失用當時的單價計算）。
     *
     * @param  array{scrap_date: string, scrap_time?: ?string, quantity: int, reason: string, note?: ?string}  $data
     *
     * @throws DomainException 目前庫存不足
     */
    public function scrap(Part $part, array $data, ?int $recordedBy): PartScrap
    {
        return DB::transaction(function () use ($part, $data, $recordedBy) {
            $allocations = $this->consume($part, (int) $data['quantity']);

            $scrap = PartScrap::create([
                'part_id' => $part->id,
                'scrap_date' => $data['scrap_date'],
                'scrap_time' => $data['scrap_time'] ?? now()->format('H:i'),
                'quantity' => (int) $data['quantity'],
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'recorded_by' => $recordedBy,
            ]);
            $scrap->batches()->createMany($allocations);
            $this->rebuild($part);   // 補登較早日期的報廢：改扣當時最早的批次，後面的使用往後順延

            return $scrap->refresh();
        });
    }

    /**
     * 取消報廢（登記錯了）：把數量加回當初扣的批次與目前庫存，再刪除這筆報廢紀錄。
     * 當初扣的批次如果已經被刪除（理論上不會，用過的批次不能刪），那部分只加回目前庫存。
     */
    public function restoreScrap(PartScrap $scrap): void
    {
        DB::transaction(function () use ($scrap) {
            Part::query()->whereKey($scrap->part_id)->lockForUpdate()->first();
            $locked = PartScrap::query()->whereKey($scrap->id)->lockForUpdate()->firstOrFail();

            foreach ($locked->batches as $batch) {
                if ($batch->part_purchase_id) {
                    PartPurchase::query()->whereKey($batch->part_purchase_id)->increment('remaining_quantity', $batch->quantity);
                }
            }

            Part::query()->whereKey($locked->part_id)->increment('current_stock', $locked->quantity);
            $part = $locked->part;
            $locked->delete();   // 批次明細跟著刪除（外鍵 cascade）
            $this->rebuild($part);
        });
    }

    /**
     * 依日期重新計算這個零件的先進先出（見類別說明）。
     *
     * 排序：發生時間（維修＝開始處理時間、保養＝執行時間、報廢＝報廢日期＋時間）由早到晚；
     * 時間相同時先登記的先扣。每一筆只能扣採購日期不晚於它的批次（同一天買的可以用）。
     *
     * $strict = true（平常）：如果有一筆原本扣得到、重新計算後在它的日期當時庫存不夠，
     * 丟出 PartStockTimelineException（呼叫端在交易裡，所以什麼都不會改）。
     * 原本就缺少批次的舊資料（扣用批次是空的）不會因此擋下，只是照樣標示缺少單價。
     * $strict = false（一次性整理舊資料的指令用）：遇到不夠的直接回傳那些紀錄，不寫入任何東西。
     *
     * @return list<array{label: string, at: Carbon}> 重新計算後在當時庫存不足的紀錄（strict 時一定是空的）
     */
    public function rebuild(Part $part, bool $strict = true): array
    {
        $part = Part::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();

        // 期初庫存（新增零件時填的庫存）視為最早就有：排在最前面，任何日期都能扣。
        $purchases = PartPurchase::query()->where('part_id', $part->id)
            ->orderBy('purchase_date')->orderBy('id')->lockForUpdate()->get()
            ->sortBy(fn (PartPurchase $p) => $p->isOpeningStock() ? 0 : 1)->values();
        $remaining = $purchases->mapWithKeys(fn (PartPurchase $p) => [$p->id => $p->quantity])->all();
        $remainingBefore = (int) $purchases->sum('remaining_quantity');

        $events = $this->timelineEvents($part);
        $shortages = [];
        $plans = [];

        foreach ($events as $event) {
            $left = $event['quantity'];
            $allocations = [];
            foreach ($purchases as $purchase) {
                if ($left === 0) {
                    break;
                }
                $notYetBought = ! $purchase->isOpeningStock() && $purchase->purchase_date->toDateString() > $event['at']->toDateString();
                if ($notYetBought || $remaining[$purchase->id] <= 0) {
                    continue;
                }
                $take = min($left, $remaining[$purchase->id]);
                $remaining[$purchase->id] -= $take;
                $allocations[] = ['part_purchase_id' => $purchase->id, 'quantity' => $take, 'unit_price' => $purchase->unit_price];
                $left -= $take;
            }
            if ($left > 0) {
                $allocations[] = ['part_purchase_id' => null, 'quantity' => $left, 'unit_price' => null];
                if (! $event['was_short']) {
                    $shortages[] = ['label' => $event['label'], 'at' => $event['at']];
                }
            }
            $plans[] = [$event, $allocations];
        }

        if ($shortages !== []) {
            if ($strict) {
                $first = $shortages[0];
                throw new PartStockTimelineException(PartStockTimelineException::PREFIX
                    . "這樣登記會導致 {$first['at']->format('Y-m-d')} 的{$first['label']}當時庫存不足（「{$part->name}」在那天以前買進的數量不夠扣）。請先確認日期、數量或其他紀錄是否正確。");
            }

            return $shortages;
        }

        foreach ($plans as [$event, $allocations]) {
            $this->writeAllocations($event, $allocations);
        }
        foreach ($purchases as $purchase) {
            if ($purchase->remaining_quantity !== $remaining[$purchase->id]) {
                $purchase->update(['remaining_quantity' => $remaining[$purchase->id]]);
            }
        }
        // 目前庫存跟著批次剩餘數量的變化調整（舊資料若本來就不相等，差額維持不變）。
        $part->update(['current_stock' => $part->current_stock + array_sum($remaining) - $remainingBefore]);

        return [];
    }

    /**
     * 這個零件所有的使用（維修、保養）與報廢，依發生時間排好。
     * 一筆維修紀錄（或保養結果、報廢）用到這個零件算一筆，跨批次拆成好幾列的會合併。
     * 批次制度以前的舊用料（每一列都沒有扣用批次、自己存單價）不參與重新計算，保持原樣。
     *
     * @return Collection<int, array{key: string, label: string, at: Carbon, created: Carbon, quantity: int, was_short: bool, rows: Collection}>
     */
    private function timelineEvents(Part $part): Collection
    {
        $events = collect();

        RepairPartUsage::query()->where('part_id', $part->id)->with('repairLog')->orderBy('id')->lockForUpdate()->get()
            ->groupBy('repair_log_id')
            ->each(function (Collection $rows) use ($events) {
                if ($rows->every(fn ($row) => $row->part_purchase_id === null)) {
                    return;   // 舊資料：不重新計算
                }
                $first = $rows->first();
                $events->push([
                    'type' => 'repair',
                    'label' => '使用（報修單 #' . ($first->repairLog?->repair_request_id ?? '?') . '）',
                    'at' => $first->repairLog?->started_at ?? $first->created_at,
                    'created' => $first->created_at,
                    'quantity' => (int) $rows->sum('quantity'),
                    'was_short' => $rows->contains(fn ($row) => $row->part_purchase_id === null),
                    'rows' => $rows,
                    'owner' => ['repair_log_id' => $first->repair_log_id],
                ]);
            });

        MaintenancePartUsage::query()->where('part_id', $part->id)->with('maintenanceResult')->orderBy('id')->lockForUpdate()->get()
            ->groupBy('maintenance_result_id')
            ->each(function (Collection $rows) use ($events) {
                if ($rows->every(fn ($row) => $row->part_purchase_id === null)) {
                    return;   // 舊資料：不重新計算
                }
                $first = $rows->first();
                $events->push([
                    'type' => 'maintenance',
                    'label' => '使用（保養工單 #' . ($first->maintenanceResult?->maintenance_order_id ?? '?') . '）',
                    'at' => $first->maintenanceResult?->executed_at ?? $first->created_at,
                    'created' => $first->created_at,
                    'quantity' => (int) $rows->sum('quantity'),
                    'was_short' => $rows->contains(fn ($row) => $row->part_purchase_id === null),
                    'rows' => $rows,
                    'owner' => ['maintenance_result_id' => $first->maintenance_result_id],
                ]);
            });

        PartScrap::query()->where('part_id', $part->id)->with('batches')->orderBy('id')->lockForUpdate()->get()
            ->each(function (PartScrap $scrap) use ($events) {
                if ($scrap->batches->isNotEmpty() && $scrap->batches->every(fn ($row) => $row->part_purchase_id === null)) {
                    return;   // 舊資料：不重新計算
                }
                // 報廢時間沒填（舊資料）就用登記當下的時間，日期仍以報廢日期為準。
                $time = $scrap->scrap_time ?: $scrap->created_at->format('H:i:s');
                $events->push([
                    'type' => 'scrap',
                    'label' => '報廢',
                    'at' => Carbon::parse($scrap->scrap_date->toDateString() . ' ' . $time),
                    'created' => $scrap->created_at,
                    'quantity' => $scrap->quantity,
                    'was_short' => $scrap->batches->contains(fn ($row) => $row->part_purchase_id === null),
                    'rows' => $scrap->batches,
                    'owner' => ['part_scrap_id' => $scrap->id],
                ]);
            });

        return $events->sort(function (array $a, array $b) {
            return [$a['at']->getTimestamp(), $a['created']->getTimestamp(), $a['rows']->first()?->id]
                <=> [$b['at']->getTimestamp(), $b['created']->getTimestamp(), $b['rows']->first()?->id];
        })->values();
    }

    /** 把重新計算的扣用批次寫回（跟原本一樣就不動）；跨批次時一批一列，保留原本的建立時間。 */
    private function writeAllocations(array $event, array $allocations): void
    {
        $current = $event['rows']->map(fn ($row) => [
            'part_purchase_id' => $row->part_purchase_id,
            'quantity' => (int) $row->quantity,
            'unit_price' => $row->unit_price === null ? null : (int) $row->unit_price,
        ])->values()->all();
        if ($current === $allocations) {
            return;
        }

        $first = $event['rows']->first();
        $model = match ($event['type']) {
            'repair' => RepairPartUsage::class,
            'maintenance' => MaintenancePartUsage::class,
            'scrap' => PartScrapBatch::class,
        };
        $model::query()->whereKey($event['rows']->pluck('id'))->delete();
        foreach ($allocations as $allocation) {
            $row = (new $model)->forceFill([...$event['owner'], ...($event['type'] === 'scrap' ? [] : ['part_id' => $first->part_id]), ...$allocation]);
            $row->created_at = $first->created_at;
            $row->save();
        }
    }
}
