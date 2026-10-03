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
 *
 * 權限代碼的命名習慣：「功能.動作」，例如 users.manage（管理用戶）、repairs.dispatch（派工）。
 * 這裡的方法都是 static，不用先建立物件，直接寫 PermissionCatalog::all() 即可。
 */
class PermissionCatalog
{
    /** 權限分組（畫面上一個分組一張卡片）：分組代碼 => 該組的權限代碼清單。 */
    public const GROUPS = [
        // 主檔管理類
        'master' => [
            'users.manage',               // 用戶主檔
            'roles.manage',               // 身分主檔
            'departments.manage',         // 部門主檔
            'classrooms.manage',          // 教室主檔
            'device-categories.manage',   // 設備類別主檔
            'devices.manage',             // 設備主檔
            'audit-logs.view',            // 查看操作紀錄
        ],
        // 報修流程類
        'repair' => [
            'repairs.create',       // 提出報修
            'repairs.dispatch',     // 派工／重新指派
            'repairs.process',      // 處理維修（開始處理、填維修紀錄）
            'repairs.accept',       // 驗收（通過結案／退回重修）
            'repairs.assignable',   // 可被指派為維修人員（出現在派工下拉選單）
            'repairs.notice_cc',    // 接收派工通知信的副本
        ],
        // 知識庫類
        'knowledge' => [
            'knowledge-base.manage',   // 新增／編輯／刪除知識庫文章
        ],
    ];

    /** 系統內建五個身分的預設權限（種子資料與舊資料補值都用這份）。admin 不用列，永遠全開。 */
    private const DEFAULTS = [
        'admin' => [],   // 系統管理員：永遠全開，不需要列
        'it_manager' => [   // 設備管理員（資訊組主管）
            'classrooms.manage', 'device-categories.manage', 'devices.manage', 'audit-logs.view',
            'repairs.create', 'repairs.dispatch', 'repairs.notice_cc', 'knowledge-base.manage',
        ],
        'technician' => ['repairs.create', 'repairs.process', 'repairs.assignable'],   // 維修人員
        'teacher' => ['repairs.create', 'repairs.accept'],                              // 教師
        'executive' => ['repairs.create', 'repairs.dispatch', 'repairs.accept'],       // 主管
    ];

    /** @return list<string> 全部權限代碼 */
    public static function all(): array
    {
        // array_values 取出每一組的清單；「...」把它們展開成參數，array_merge 再串成一份大清單。
        return array_merge(...array_values(self::GROUPS));
    }

    /** 這個權限代碼是否存在於清單中（用來擋掉偽造的代碼）。 */
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
        // array_filter 留下「是文字且存在於清單」的項目 → array_unique 去重 → array_values 重新編號。
        return array_values(array_unique(array_filter($permissions, fn ($p) => is_string($p) && self::exists($p))));
    }
}
