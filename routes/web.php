<?php

/*
 * 網站的「網址地圖」：哪個網址 → 交給哪個 Controller 的哪個方法。
 *
 * 【怎麼讀這個檔案】
 * - Route::get('/網址', ...)    ：瀏覽器「開啟」某個網址（讀取畫面）。
 * - Route::post(...)            ：瀏覽器「送出表單」（新增資料、登入...）。
 * - Route::patch / put / delete ：局部修改 / 整筆修改 / 刪除（表單會用隱藏欄位偽裝成這些動作）。
 * - Route::resource('名稱', Controller::class)：一次建立 7 個標準網址
 *     index 列表、create 新增表單、store 儲存新增、show 檢視、edit 編輯表單、update 儲存修改、destroy 刪除。
 *     ->except([...]) 排除不要的、->only([...]) 只要指定的。
 * - ->name('xxx.yyy')           ：幫這個網址取名字，之後程式與畫面用 route('xxx.yyy') 產生網址，
 *                                  這樣網址改了只要改這裡，不用到處找。
 * - ->middleware('can:權限代碼') ：這個網址要有該權限才進得去，沒有權限回 403（權限由「身分主檔」勾選決定）。
 * - Route::middleware(...)->group(function () { ... })：這一包網址共用同一個關卡。
 *
 * 【想新增一個功能頁面】1. 建 Controller；2. 在下面加一行路由並加上適當的 can:；
 * 3. 若是新的權限，先到 app/Support/PermissionCatalog.php 加上權限代碼。
 */

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceCategoryController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceEntryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\RepairLogController;
use App\Http\Controllers\RepairRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 網站首頁（網址 /）：Laravel 預設的歡迎頁。
Route::get('/', function () {
    return view('welcome');
});

// i18n 語言切換：把選的語言存進 session，然後導回原本那一頁。
// 只接受 SetLocale 中介層支援的代碼，其他一律忽略，避免任意字串污染 session。
// 放在 auth 群組外面，這樣未登入的登入頁也能切換語言。
Route::get('locale/{locale}', function (string $locale) {
    // {locale} 是網址上的變數，例如 /locale/en 時 $locale 就是 'en'。
    if (in_array($locale, ['zh_TW', 'en'], true)) {
        session(['locale' => $locale]);   // 存進 session，之後每個請求都會套用
    }

    return back();   // 回到使用者原本在的那一頁
})->name('locale.switch');

// 舊網址相容：報修路由曾經叫 /repair-requests，後來改成 /repairs（對齊導覽選單的命名）。
// 瀏覽器書籤、歷史紀錄裡的舊網址自動轉址過去，不要讓使用者看到 404。
// {path?} 的問號代表「這段可有可無」，where('path', '.*') 讓它可以包含斜線（例如 /repair-requests/5/edit）。
// 301 代表「永久搬家」，瀏覽器與搜尋引擎之後會直接記住新網址。
Route::get('repair-requests/{path?}', function (?string $path = null) {
    return redirect('/repairs' . ($path ? '/' . $path : ''), 301);
})->where('path', '.*');

// 登入、登出：不需要登入就能進（否則沒人進得去登入頁）。
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');   // 顯示登入表單
Route::post('/login', [LoginController::class, 'login']);                          // 送出帳號密碼
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');        // 登出

// 權限一律用 `can:權限代碼` 保護（權限清單見 App\Support\PermissionCatalog，
// 每個身分有哪些權限由「身分主檔」勾選決定；系統管理員永遠全開）。
// 下面整個群組套用 'auth'：一定要先登入，沒登入會被導到登入頁；
// 再套用 'active'：帳號被停用的人（包含靠「記住我」cookie 自動登入的）會被強制登出。
Route::middleware(['auth', 'active'])->group(function () {
    // 主控台（登入後的首頁）：所有登入者都能看，卡片與圖表依權限顯示不同內容。
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // 設備入口頁：QR / URL 掃描後看到的頁面，任何登入角色都能看，不限管理端
    // {device:device_code} 代表「網址上的值用 device_code 欄位去找設備」，所以網址是 /d/設備編號。
    Route::get('/d/{device:device_code}', [DeviceEntryController::class, 'show'])->name('devices.entry');

    // ---- 主檔管理 ----
    // 教室主檔：沒有 show（檢視）與 destroy（刪除），教室只能停用不能刪。
    Route::middleware('can:classrooms.manage')->group(function () {
        Route::resource('classrooms', ClassroomController::class)->except(['show', 'destroy']);
        Route::patch('classrooms/{classroom}/toggle', [ClassroomController::class, 'toggle'])->name('classrooms.toggle');   // 啟用／停用
    });

    // 設備類別主檔：沒有 show（類別沒有獨立檢視頁）。
    Route::middleware('can:device-categories.manage')->group(function () {
        Route::resource('device-categories', DeviceCategoryController::class)->except(['show']);
    });

    // 設備主檔：沒有 destroy，改用 disable（停用 + 軟刪除）。
    Route::middleware('can:devices.manage')->group(function () {
        Route::resource('devices', DeviceController::class)->except(['destroy']);
        Route::patch('devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable');   // 停用設備
        Route::get('devices/{device}/qrcode', [DeviceController::class, 'qrcode'])->name('devices.qrcode');        // 產生 QR Code 圖片
    });

    // 操作紀錄：只能查看，需要 audit-logs.view 權限。
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:audit-logs.view')->name('audit-logs.index');

    // 用戶主檔（帳號管理）：可以改任何人的身分與密碼，所以是獨立的權限。
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');                          // 啟用／停用
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');   // 重設密碼
        // withTrashed()：還原的對象是已被軟刪除的帳號，預設的路由綁定找不到它們。
        Route::patch('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
    });

    // 身分主檔：決定每個身分開放哪些權限（像 Discord 的身分組）。
    Route::middleware('can:roles.manage')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    // 部門主檔：用戶主檔、教室主檔的部門下拉選單都從這裡來。
    Route::middleware('can:departments.manage')->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::patch('departments/{department}/toggle', [DepartmentController::class, 'toggle'])->name('departments.toggle');   // 啟用／停用
    });

    // ---- feature/repair（彭仕衡）：自助知識庫 + 報修 + 維修主流程 ----
    // 路由名稱用 repairs（不是 repair-requests），對齊林政寬 nav-links 的
    // Route::has('repairs.index') 跟 DeviceEntryController 導去報修的連結命名。

    // 知識庫：所有登入者都能看，新增／修改／刪除需要 knowledge-base.manage。
    // 管理用的路由一定要先註冊，create 才不會被 {knowledge_base} 當成文章 id。
    Route::resource('knowledge-base', KnowledgeBaseController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:knowledge-base.manage');
    Route::resource('knowledge-base', KnowledgeBaseController::class)->only(['index', 'show']);

    // 「問題已解決」最小流程：只做跳轉並顯示感謝訊息，不記錄額外狀態。
    Route::get('knowledge-base/{knowledge_base}/resolved', [KnowledgeBaseController::class, 'resolved'])
        ->name('knowledge-base.resolved');

    // 提出報修需要 repairs.create。device-lookup 是新增報修頁「掃描設備條碼」用的 JSON 查詢，
    // 用 ?code= 查詢參數而不是網址片段，因為掃到的內容可能是整串網址（含斜線）。
    // 這兩條一定要放在下面 index/show 之前，不然 repairs/create 會被當成 repairs/{repair_request}。
    Route::middleware('can:repairs.create')->group(function () {
        Route::get('repairs/device-lookup', [RepairRequestController::class, 'deviceLookup'])
            ->name('repairs.device-lookup');
        Route::resource('repairs', RepairRequestController::class)
            ->parameters(['repairs' => 'repair_request'])
            ->only(['create', 'store']);
    });

    // 看板與詳細頁：所有登入者都能看。
    // ->parameters(...) 讓 resource 路由的網址參數也叫 repair_request，跟下面
    // assign/start/reassign 等動作路由的參數名稱一致（預設縮寫成 {repair} 會讓隱性路由模型綁定對不上）。
    Route::resource('repairs', RepairRequestController::class)
        ->parameters(['repairs' => 'repair_request'])
        ->only(['index', 'show']);

    // 附件下載（報修與維修紀錄的照片、影片）：檔案存在私有磁碟，不能直接用網址開，
    // 這裡先檢查「能不能檢視所屬的報修單」才輸出檔案（見 AttachmentController）。
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // 派工與重新指派（重新指派不改變案件狀態）：需要 repairs.dispatch。
    Route::middleware('can:repairs.dispatch')->group(function () {
        Route::post('repairs/{repair_request}/assign', [RepairRequestController::class, 'assign'])->name('repairs.assign');
        Route::post('repairs/{repair_request}/reassign', [RepairRequestController::class, 'reassign'])->name('repairs.reassign');
    });

    // 維修人員處理：開始處理、填維修紀錄（送出後自動推進到待驗收）：需要 repairs.process。
    Route::middleware('can:repairs.process')->group(function () {
        Route::post('repairs/{repair_request}/start', [RepairRequestController::class, 'start'])->name('repairs.start');
        Route::get('repairs/{repair_request}/repair-logs/create', [RepairLogController::class, 'create'])->name('repair-logs.create');
        Route::post('repairs/{repair_request}/repair-logs', [RepairLogController::class, 'store'])->name('repair-logs.store');
    });

    // 驗收通過結案 / 驗收不通過退回重修：需要 repairs.accept。
    Route::middleware('can:repairs.accept')->group(function () {
        Route::post('repairs/{repair_request}/complete', [RepairRequestController::class, 'complete'])->name('repairs.complete');
        Route::post('repairs/{repair_request}/reject', [RepairRequestController::class, 'reject'])->name('repairs.reject');
    });
});
