<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 身分勾選的權限（一列 = 一個身分的一個權限代碼）。
 * 平常不直接操作這個模型：用 $role->permissions（陣列）讀寫就好，Role 模型會幫忙同步這張表。
 */
class RolePermission extends Model
{
    protected $fillable = ['role_id', 'permission'];

    /** 這個權限屬於哪一個身分。 */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
