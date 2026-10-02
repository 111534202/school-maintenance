<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DeviceCategoryController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceEntryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\RepairLogController;
use App\Http\Controllers\RepairRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// i18n 語言切換：把選的語言存進 session，然後導回原本那一頁。
// 只接受 SetLocale 中介層支援的代碼，其他一律忽略，避免任意字串污染 session。
// 放在 auth 群組外面，這樣未登入的登入頁也能切換語言。
Route::get('locale/{locale}', function (string $locale) {
    if (in_array($locale, ['zh_TW', 'en'], true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // 設備入口頁：QR / URL 掃描後看到的頁面，任何登入角色都能看，不限管理端
    Route::get('/d/{device:device_code}', [DeviceEntryController::class, 'show'])->name('devices.entry');

    // 管理端入口：教室、設備類別、設備管理、audit log 查詢僅開放 admin / it_manager
    Route::middleware('role:admin,it_manager')->group(function () {
        Route::resource('classrooms', ClassroomController::class)->except(['show', 'destroy']);
        Route::patch('classrooms/{classroom}/toggle', [ClassroomController::class, 'toggle'])->name('classrooms.toggle');

        Route::resource('device-categories', DeviceCategoryController::class)->except(['show']);

        Route::resource('devices', DeviceController::class)->except(['destroy']);
        Route::patch('devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable');
        Route::get('devices/{device}/qrcode', [DeviceController::class, 'qrcode'])->name('devices.qrcode');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // feature/repair（彭仕衡）：自助知識庫 + 報修 + 維修主流程。
    // devices/users 表合併後改用 auth，所有動作都要先登入；路由名稱從 repair-requests
    // 改成 repairs，對齊林政寬 nav-links 分享選單（Route::has('repairs.index')）跟
    // DeviceEntryController 導去報修的連結命名。
    Route::resource('knowledge-base', KnowledgeBaseController::class);

    // 「問題已解決」最小流程：只做跳轉並顯示感謝訊息，不記錄額外狀態。
    Route::get('knowledge-base/{knowledge_base}/resolved', [KnowledgeBaseController::class, 'resolved'])
        ->name('knowledge-base.resolved');

    // 設備條碼／QR 掃描查詢（供新增報修頁的「掃描設備條碼」欄位即時查詢用），回傳 JSON。
    // 用 ?code= 查詢參數而不是網址片段，因為掃到的內容可能是整串網址（含斜線）；
    // 一定要放在下面 resource 之前，不然 repairs/device-lookup 會被當成 repairs/{repair_request}。
    Route::get('repairs/device-lookup', [RepairRequestController::class, 'deviceLookup'])
        ->name('repairs.device-lookup');

    // 本週不做編輯、刪除報修單本身，狀態改變一律走下面的 assign/start/repair-logs 動作路由。
    // ->parameters(...) 讓 resource 路由的網址參數也叫 repair_request，跟下面
    // assign/start/reassign 等動作路由的參數名稱一致（曾經因為預設縮寫成 {repair}
    // 導致 show() 的隱性路由模型綁定對不上，這裡明確指定就不會再犯）。
    Route::resource('repairs', RepairRequestController::class)
        ->parameters(['repairs' => 'repair_request'])
        ->only(['index', 'create', 'store', 'show']);

    // 人工派工（新報修 -> 已派工）與開始處理（已派工 -> 處理中）。
    Route::post('repairs/{repair_request}/assign', [RepairRequestController::class, 'assign'])
        ->name('repairs.assign');
    Route::post('repairs/{repair_request}/start', [RepairRequestController::class, 'start'])
        ->name('repairs.start');

    // 重新指派（依《第四週個人工作計畫》第 1 項）：換維修人員/處理日期，不改變案件狀態。
    Route::post('repairs/{repair_request}/reassign', [RepairRequestController::class, 'reassign'])
        ->name('repairs.reassign');

    // 維修填單，送出後自動把案件推進到「待驗收」。
    Route::get('repairs/{repair_request}/repair-logs/create', [RepairLogController::class, 'create'])
        ->name('repair-logs.create');
    Route::post('repairs/{repair_request}/repair-logs', [RepairLogController::class, 'store'])
        ->name('repair-logs.store');

    // 驗收通過結案 / 驗收不通過退回重修（依《第三週個人工作計畫》第 2、3 項）。
    Route::post('repairs/{repair_request}/complete', [RepairRequestController::class, 'complete'])
        ->name('repairs.complete');
    Route::post('repairs/{repair_request}/reject', [RepairRequestController::class, 'reject'])
        ->name('repairs.reject');
});
