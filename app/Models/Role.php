<?php

namespace App\Models;

use App\Support\PermissionCatalog;           // 權限清單（有哪些權限代碼）
use Illuminate\Database\Eloquent\Builder;    // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Casts\Attribute;   // 自訂欄位的讀寫方式（permissions 用）
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 身分（身分組）。每個身分有一份勾選的權限清單（permissions，見 PermissionCatalog），
 * 使用者的所有功能存取都由他的身分決定。系統管理員（role_code = admin）永遠擁有全部權限。
 *
 * 欄位說明：name 顯示名稱；role_code 內部代碼（系統內建身分固定，自訂身分是自動產生的）；
 * description 說明；is_system 是否系統內建（內建的不能刪除）。
 * 勾選的權限存在 role_permissions 資料表（一列一個權限，第一正規化）；程式裡仍然用 $role->permissions
 * 讀寫「權限代碼的陣列」，Role 會自動同步那張表，所以呼叫端不用改。
 */
class Role extends Model
{
    // 允許批次寫入的欄位白名單。
    protected $fillable = ['name', 'role_code', 'description', 'is_system', 'permissions'];

    /** 還沒存進資料庫的權限清單（先暫存，等 save() 之後才寫進 role_permissions）。 */
    protected ?array $pendingPermissions = null;

    // 儲存身分之後，把暫存的權限清單同步到 role_permissions（多的刪掉、缺的補上）。
    protected static function booted(): void
    {
        static::saved(function (Role $role) {
            if ($role->pendingPermissions === null) {
                return;
            }

            $wanted = $role->pendingPermissions;
            $role->pendingPermissions = null;

            $role->rolePermissions()->whereNotIn('permission', $wanted)->delete();
            $existing = $role->rolePermissions()->pluck('permission')->all();
            foreach (array_diff($wanted, $existing) as $permission) {
                $role->rolePermissions()->create(['permission' => $permission]);
            }
            $role->unsetRelation('rolePermissions');   // 下次讀取時重新從資料庫取得最新的
        });
    }

    // 型別轉換：is_system 轉成 true/false；permissions 在資料庫是 JSON 文字，讀出來自動變 PHP 陣列、存進去自動轉回 JSON。
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** 這個身分勾選的權限資料列（role_permissions）。 */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * permissions：權限代碼的陣列（['repairs.create', 'devices.manage']）。
     * 讀：從 role_permissions 取出；寫：先暫存，等儲存身分時才寫進 role_permissions（見上面的 booted）。
     */
    protected function permissions(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->pendingPermissions !== null) {
                    return $this->pendingPermissions;
                }

                return $this->exists ? $this->rolePermissions->pluck('permission')->all() : [];
            },
            set: function ($value) {
                $this->pendingPermissions = array_values(array_unique(array_filter((array) $value, 'is_string')));

                return [];   // roles 資料表已經沒有 permissions 欄位，什麼都不用寫進 roles
            },
        );
    }

    /** 屬於這個身分的所有用戶。 */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** 這個身分是不是「系統管理員」：永遠擁有全部權限，畫面上也不能取消勾選。 */
    public function isAdmin(): bool
    {
        return $this->role_code === 'admin';
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
        // whereHas：有一筆 role_permissions 屬於這個身分、而且權限代碼符合。
        return $query->whereHas('rolePermissions', fn (Builder $q) => $q->where('permission', $permission));
    }
}
