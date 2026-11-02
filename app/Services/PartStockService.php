<?php

namespace App\Services;

use App\Models\Part;
use App\Models\PartPurchase;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * 零件庫存的唯一進出口：進貨（採購紀錄）與用料（維修、保養）都要經過這裡，
 * 才能讓「零件的目前庫存」永遠等於「各採購批次的剩餘數量加總」。
 *
 * 成本計算採先進先出（FIFO）：用料時先扣最早買進、還有剩的批次。
 * 例：10/10 買 2 條（20 元）、10/12 買 2 條（30 元）
 *     10/15 用 1 條 → 20 元；10/17 用 2 條 → 20 元＋30 元＝50 元。
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

            return $purchase;
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
                throw new DomainException('這一批已經有 ' . $locked->usedQuantity() . ' 個被維修或保養用掉，不能再修改數量與單價（日期、廠商、備註仍可修改）。');
            }

            $difference = (int) $data['quantity'] - $locked->quantity;

            $locked->update([
                ...$data,
                'remaining_quantity' => $locked->remaining_quantity + $difference,
            ]);

            if ($difference !== 0) {
                Part::query()->whereKey($locked->part_id)->increment('current_stock', $difference);
            }

            return $locked;
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
                throw new DomainException('這一批已經有 ' . $locked->usedQuantity() . ' 個被維修或保養用掉，不能刪除。');
            }

            Part::query()->whereKey($locked->part_id)->decrement('current_stock', $locked->quantity);
            $locked->attachments()->delete();
            $locked->delete();
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
}
