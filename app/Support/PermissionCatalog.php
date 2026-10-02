<?php

namespace App\Support;

/**
 * 全系統「權限選項」的清單（像 Discord 身分組裡可以勾選的權限）。
 *
 * 身分主檔（roles）新增身分後，在畫面上從這份清單勾選要開放哪些權限，勾選結果存在
 * roles.permissions（JSON 陣列）。程式裡判斷權限一律用 Laravel 的 Gate：
 *   @can('users.manage')、Route::...->middleware('can:users.manage')、$user->can('...')
 * （Gate 的註冊在 AppServiceProvider，會逐一對應這份清單裡的每個權限）。
 *
 * 要新增一個權限：1. 在下面 GROUPS 加上代碼　2. 在 lang 資料夾底下各語言的 permissions.php 補上名稱
 * 3. 在需要保護的路由加上 can:xxx。系統管理員（slug = admin）永遠擁有全部權限，不需要勾選。
 */
class PermissionCatalog
{
    /** 權限分組（畫面上一個分組一張卡片）：分組代碼 => 該組的權限代碼清單。 */
    public const GROUPS = [
        'master' => [
            'users.manage',
            'roles.manage',
            'departments.manage',
            'classrooms.manage',
            'device-categories.manage',
            'devices.manage',
            'audit-logs.view',
        ],
        'repair' => [
            'repairs.create',
            'repairs.dispatch',
            'repairs.process',
            'repairs.accept',
            'repairs.assignable',
            'repairs.notice_cc',
        ],
        'knowledge' => [
            'knowledge-base.manage',
        ],
    ];

    /** 系統內建五個身分的預設權限（種子資料與舊資料補值都用這份）。admin 不用列，永遠全開。 */
    private const DEFAULTS = [
        'admin' => [],
        'it_manager' => [
            'classrooms.manage', 'device-categories.manage', 'devices.manage', 'audit-logs.view',
            'repairs.create', 'repairs.dispatch', 'repairs.notice_cc', 'knowledge-base.manage',
        ],
        'technician' => ['repairs.create', 'repairs.process', 'repairs.assignable'],
        'teacher' => ['repairs.create', 'repairs.accept'],
        'executive' => ['repairs.create', 'repairs.dispatch', 'repairs.accept'],
    ];

    /** @return list<string> 全部權限代碼 */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /** @return list<string> 內建身分的預設權限；不是內建身分就回傳空陣列。 */
    public static function defaultsFor(string $slug): array
    {
        return self::DEFAULTS[$slug] ?? [];
    }

    /** 過濾使用者送來的權限勾選：只保留清單裡真的存在的代碼，去除重複。 */
    public static function sanitize(array $permissions): array
    {
        return array_values(array_unique(array_filter($permissions, fn ($p) => is_string($p) && self::exists($p))));
    }
}
