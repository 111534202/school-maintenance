<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * 給「下拉選單」用的部門：只列啟用中的部門；編輯既有資料時，把該資料目前
     * 已選的部門（就算後來被停用了）也一起列出來，才不會因為停用而讓選項消失。
     */
    public function scopeForSelect(Builder $query, ?int $currentId = null): Builder
    {
        return $query->where(function (Builder $q) use ($currentId) {
            $q->where('is_active', true);
            if ($currentId) {
                $q->orWhere('id', $currentId);
            }
        })->orderBy('name');
    }
}
