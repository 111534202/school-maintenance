<?php

namespace App\Models;

use App\Support\PermissionCatalog;           // 權限清單（有哪些權限代碼）
use Illuminate\Database\Eloquent\Builder;    // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Model;

/**
 * 身分（身分組）。每個身分有一份勾選的權限清單（permissions，見 PermissionCatalog），
 * 使用者的所有功能存取都由他的身分決定。系統管理員（slug = admin）永遠擁有全部權限。
 *
 * 欄位說明：name 顯示名稱；slug 內部代碼（系統內建身分固定，自訂身分是自動產生的）；
 * description 說明；is_system 是否系統內建（內建的不能刪除）；permissions 勾選的權限代碼清單（JSON）。
 */
class Role extends Model
{
    // 允許批次寫入的欄位白名單。
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'permissions'];

    // 型別轉換：is_system 轉成 true/false；permissions 在資料庫是 JSON 文字，讀出來自動變 PHP 陣列、存進去自動轉回 JSON。
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /** 屬於這個身分的所有用戶。 */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** 這個身分是不是「系統管理員」：永遠擁有全部權限，畫面上也不能取消勾選。 */
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    /** 這個身分有沒有某個權限：管理員一律有；其他身分看 permissions 清單有沒有勾選。 */
    public function hasPermission(string $permission): bool
    {
        // ?? [] 是「permissions 為空值時當成空清單」；最後的 true 代表嚴格比對（型別也要一樣）。
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
     *
     * 這是「查詢範圍（scope）」：使用時寫 Role::withPermission('repairs.assignable')。
     */
    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        // whereJsonContains：查詢 JSON 欄位裡「包含」這個值的資料列。
        return $query->whereJsonContains('permissions', $permission);
    }
}
