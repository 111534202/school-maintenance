<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 身分勾選的權限（一列代表一個身分的一個權限代碼）。
 */
class RolePermission extends Model
{
    protected $fillable = ['role_id', 'permission'];

    /** 這個權限所屬的身分。 */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}