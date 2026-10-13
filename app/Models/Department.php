<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;   // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;

/**
 * 部門主檔（departments 資料表）。用戶與教室都會歸屬某個部門，
 * 它們表單裡的「部門」下拉選單就是從這裡讀（見下方 scopeForSelect）。
 */
class Department extends Model
{
    // 允許批次寫入的欄位：名稱、代碼、說明、是否啟用。
    protected $fillable = ['name', 'code', 'description', 'is_active'];

    // 型別轉換（新寫法，用方法回傳）：is_active 自動在資料庫的 0/1 與 PHP 的 true/false 之間轉換。
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** 這個部門底下的所有教室。 */
    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    /** 屬於這個部門的所有用戶。 */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * 給「下拉選單」用的部門：只列啟用中的部門；編輯既有資料時，把該資料目前
     * 已選的部門（就算後來被停用了）也一起列出來，才不會因為停用而讓選項消失。
     *
     * 這是「查詢範圍（scope）」：方法名稱以 scope 開頭，使用時去掉 scope 並把第一個字母改小寫，
     * 例如 Department::forSelect($目前部門編號)->get()。
     */
    public function scopeForSelect(Builder $query, ?int $currentId = null): Builder
    {
        // 條件：「啟用中」或「就是目前已選的那個」。包在括號裡，避免「或」影響外面其他條件。
        return $query->where(function (Builder $q) use ($currentId) {
            $q->where('is_active', true);
            if ($currentId) {
                $q->orWhere('id', $currentId);
            }
        })->orderBy('name');   // 依名稱排序
    }
}
