<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 身分（身分組）。每個身分有一份勾選的權限清單（permissions，見 PermissionCatalog），
 * 使用者的所有功能存取都由他的身分決定。系統管理員（slug = admin）永遠擁有全部權限。
 */
class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'permissions'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** 這個身分是不是「系統管理員」：永遠擁有全部權限，畫面上也不能取消勾選。 */
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissions ?? [], true);
    }

    /** 這個身分實際開放的權限代碼（管理員 = 全部）。 */
    public function effectivePermissions(): array
    {
        return $this->isAdmin() ? PermissionCatalog::all() : ($this->permissions ?? []);
    }

    /**
     * 「明確勾選」了某個權限的身分。管理員的全開是隱含的，所以不算在內——
     * 例如「可被指派為維修人員」只會列出有勾選的身分，不會把系統管理員也列進去。
     */
    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->whereJsonContains('permissions', $permission);
    }
}
