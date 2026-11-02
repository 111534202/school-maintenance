<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * 零件採購紀錄（進貨批次）。
 * 總價＝數量×單價，用 totalPrice() 計算，不另外存。
 * remaining_quantity 是這一批還沒被用掉的數量（先進先出扣料時會減少）。
 * 數量、單價、剩餘數量只能透過 PartStockService 修改，才能同步更新零件的目前庫存。
 */
class PartPurchase extends Model
{
    protected $fillable = [
        'purchase_date',
        'vendor_name',
        'quantity',
        'unit_price',
        'remaining_quantity',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'remaining_quantity' => 'integer',
        ];
    }

    public function totalPrice(): int
    {
        return $this->quantity * $this->unit_price;
    }

    /** 已經被用掉的數量。 */
    public function usedQuantity(): int
    {
        return $this->quantity - $this->remaining_quantity;
    }

    /** 這一批是否已經被用過（用過就不能改數量、單價，也不能刪除）。 */
    public function isUsed(): bool
    {
        return $this->usedQuantity() > 0;
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** 發票、收據等附件（沿用報修共用的 attachments 資料表）。 */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** 刪除這筆採購的附件：資料列和硬碟上的檔案一起刪掉，不留下沒人用的檔案。 */
    public function deleteAttachments(): void
    {
        foreach ($this->attachments()->get() as $attachment) {
            if ($disk = $attachment->storageDisk()) {
                Storage::disk($disk)->delete($attachment->file_path);
            }
            $attachment->delete();
        }
    }

    public function repairPartUsages(): HasMany
    {
        return $this->hasMany(RepairPartUsage::class);
    }

    public function maintenancePartUsages(): HasMany
    {
        return $this->hasMany(MaintenancePartUsage::class);
    }
}
