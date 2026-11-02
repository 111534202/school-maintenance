<?php

namespace App\Models;

use App\Support\PermissionCatalog;           // 權限清單（有哪些權限代碼）
use Illuminate\Database\Eloquent\Builder;    // 查詢建構器的型別（scope 方法會用到）
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 身分（身分組）。每個身分有一份勾選的權限清單（permissions，見 PermissionCatalog），
 * 使用者的所有功能存取都由他的身分決定。系統管理員（role_code = admin）永遠擁有全部權限。
 *
 * 欄位說明：name 顯示名稱；role_code 內部代碼（系統內建身分固定，自訂身分是自動產生的）；
 * description 說明；is_system 是否系統內建（內建的不能刪除）；權限資料實際存放在 role_permissions 資料表。
 */
class Role extends Model
{
    /** 尚未同步到資料庫的權限清單。 */
    protected ?array $pendingPermissions = null;

    /** 儲存角色後，將權限清單同步到 role_permissions。 */
    protected static function booted(): void
    {
        static::saved(function (Role $role) {
            if ($role->pendingPermissions === null) {
                return;
            }

            $wanted = $role->pendingPermissions;
            $role->pendingPermissions = null;

            // 空清單代表取消全部權限，必須明確刪除所有舊資料。
            if ($wanted === []) {
                $role->rolePermissions()->delete();
            } else {
                $role->rolePermissions()
                    ->whereNotIn('permission', $wanted)
                    ->delete();
            }

            $existing = $role->rolePermissions()->pluck('permission')->all();

            foreach (array_diff($wanted, $existing) as $permission) {
                $role->rolePermissions()->create([
                    'permission' => $permission,
                ]);
            }

            $role->unsetRelation('rolePermissions');
        });
    }

    // 允許批次寫入的欄位白名單。
    protected $fillable = ['name', 'role_code', 'description', 'is_system', 'permissions'];

    // 型別轉換：is_system 轉成 true/false；permissions 由 Accessor 與 role_permissions 關聯處理。
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** 這個身分勾選的權限資料列。 */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * permissions 仍以陣列形式供 Controller、View 與其他程式使用，
     * 實際資料則存放在 role_permissions。
     */
    protected function permissions(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->pendingPermissions !== null) {
                    return $this->pendingPermissions;
                }

                return $this->exists
                    ? $this->rolePermissions->pluck('permission')->all()
                    : [];
            },
            set: function ($value) {
                $this->pendingPermissions = array_values(array_unique(
                    array_filter((array) $value, 'is_string')
                ));

                return [];
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
        // whereJsonContains：查詢 JSON 欄位裡「包含」這個值的資料列。
        return $query->whereHas('rolePermissions', fn (Builder $q) => $q->where('permission', $permission));
    }
}
