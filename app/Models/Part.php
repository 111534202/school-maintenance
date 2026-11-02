<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 零件（備品）主檔。
 *
 * - part_code：零件編號（PART-1、PART-2…，依新增順序），新增時由系統自動產生、之後不能修改。
 *   同一種零件的不同規格要建成不同筆零件，例如「HDMI 線 2 公尺」與「HDMI 線 5 公尺」是兩個編號。
 * - current_stock：目前庫存。它等於所有採購批次的剩餘數量加總，只能透過 PartStockService
 *   進貨（新增採購）或用料（維修、保養）來改變，畫面上不能直接修改。
 * - 單價不存在零件上，而是存在每一筆採購批次（part_purchases），成本採先進先出計算。
 */
class Part extends Model
{
    public const CODE_PREFIX = 'PART-';

    // part_code 不放在 fillable：只能由系統產生，表單送來的值一律不接受。
    // unit_price 不是資料表欄位：只在新增零件時當作「期初庫存的單價」，存成第一筆採購批次。
    protected $fillable = [
        'name',
        'specification',
        'brand',
        'model',
        'current_stock',
        'safety_stock',
        'unit_price',
    ];

    /** 新增零件時暫存的期初單價（不寫進 parts 資料表）。 */
    protected ?int $openingUnitPrice = null;

    protected static function booted(): void
    {
        static::creating(function (self $part) {
            // 新增零件時，如果還沒有編號，就自動給下一個編號。
            if (blank($part->part_code)) {
                $part->part_code = self::nextCode();
            }

            // 期初單價先拿出來暫存，避免被當成欄位寫進 parts。
            if (array_key_exists('unit_price', $part->attributes)) {
                $part->openingUnitPrice = (int) $part->attributes['unit_price'];
                unset($part->attributes['unit_price']);
            }
        });

        // 有期初庫存就建立一筆「期初庫存」批次，之後用料才有單價可以依先進先出計算。
        static::created(function (self $part) {
            if ($part->current_stock > 0) {
                $part->purchases()->create([
                    'purchase_date' => now()->toDateString(),
                    'vendor_name' => null,
                    'quantity' => $part->current_stock,
                    'unit_price' => $part->openingUnitPrice ?? 0,
                    'remaining_quantity' => $part->current_stock,
                    'note' => '期初庫存',
                ]);
            }
        });
    }

    /** 下一個零件編號：取目前最大的編號再加一（PART-3 → PART-4）。 */
    public static function nextCode(): string
    {
        $last = self::query()
            ->where('part_code', 'like', self::CODE_PREFIX . '%')
            ->orderByRaw('LENGTH(part_code) DESC')   // 沒有補零，PART-10 比 PART-9 長，先比長度才不會排錯
            ->orderByDesc('part_code')
            ->value('part_code');

        $number = $last ? ((int) substr($last, strlen(self::CODE_PREFIX))) + 1 : 1;

        return self::CODE_PREFIX . $number;
    }

    /** 標題用的完整名稱：PART-1 HDMI 線（2公尺）。 */
    public function displayName(): string
    {
        return trim($this->part_code . ' ' . $this->name . ($this->specification ? "（{$this->specification}）" : ''));
    }

    /** 品牌／型號合併顯示；兩個都沒填就回傳 null。 */
    public function brandModel(): ?string
    {
        $text = implode('／', array_filter([$this->brand, $this->model]));

        return $text === '' ? null : $text;
    }

    public function isLowStock(): bool
    {
        return $this->current_stock < $this->safety_stock;
    }

    /**
     * 平均單價：目前庫存（各批次剩餘數量）的加權平均單價；沒有庫存時回傳 null。
     * 列表頁會用 withStockValue() 先算好，避免每一筆零件各查一次資料庫。
     */
    public function averageUnitPrice(): ?float
    {
        $remaining = $this->stock_remaining ?? $this->purchases()->sum('remaining_quantity');
        $value = $this->stock_value ?? $this->purchases()->selectRaw('SUM(remaining_quantity * unit_price) AS v')->value('v');

        return $remaining > 0 ? round($value / $remaining, 1) : null;
    }

    /** 查詢時一併算出各零件的剩餘數量與庫存價值（給平均單價用）。 */
    public function scopeWithStockValue($query)
    {
        return $query
            ->withSum('purchases as stock_remaining', 'remaining_quantity')
            ->addSelect(['stock_value' => PartPurchase::query()
                ->selectRaw('COALESCE(SUM(remaining_quantity * unit_price), 0)')
                ->whereColumn('part_purchases.part_id', 'parts.id')]);
    }

    /** 採購紀錄（進貨批次）。 */
    public function purchases(): HasMany
    {
        return $this->hasMany(PartPurchase::class);
    }

    /** 維修用料明細。 */
    public function repairPartUsages(): HasMany
    {
        return $this->hasMany(RepairPartUsage::class, 'part_id');
    }

    /** 保養用料明細。 */
    public function maintenancePartUsages(): HasMany
    {
        return $this->hasMany(MaintenancePartUsage::class, 'part_id');
    }
}
