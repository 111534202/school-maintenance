<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DeviceCategoryController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceEntryController;
use App\Http\Controllers\MaintenanceItemController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\MaintenanceResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // 設備入口頁：QR / URL 掃描後看到的頁面，任何登入角色都能看，不限管理端
    Route::get('/d/{device:device_code}', [DeviceEntryController::class, 'show'])->name('devices.entry');

    // 保養項目（maintenance_items）— 第 1 週任務 1
    // 工程實作欄位：暫不限定角色，任何登入使用者皆可操作；
    // 待全組確認保養模組的角色權限規則後，再視需要加上 role 中介層。
    Route::prefix('maintenance-items')->name('maintenance-items.')->group(function () {
        Route::get('/', [MaintenanceItemController::class, 'index'])->name('index');
        Route::get('/create', [MaintenanceItemController::class, 'create'])->name('create');
        Route::post('/', [MaintenanceItemController::class, 'store'])->name('store');
        Route::get('/{maintenanceItem}/edit', [MaintenanceItemController::class, 'edit'])->name('edit');
        Route::put('/{maintenanceItem}', [MaintenanceItemController::class, 'update'])->name('update');
        Route::patch('/{maintenanceItem}/toggle-status', [MaintenanceItemController::class, 'toggleStatus'])->name('toggle-status');
    });

    // 保養計畫（maintenance_plans）— 第 1 週任務 2、3
    Route::prefix('maintenance-plans')->name('maintenance-plans.')->group(function () {
        Route::get('/', [MaintenancePlanController::class, 'index'])->name('index');
        Route::get('/create', [MaintenancePlanController::class, 'create'])->name('create');
        Route::post('/', [MaintenancePlanController::class, 'store'])->name('store');
        Route::get('/{maintenancePlan}/edit', [MaintenancePlanController::class, 'edit'])->name('edit');
        Route::put('/{maintenancePlan}', [MaintenancePlanController::class, 'update'])->name('update');
        Route::patch('/{maintenancePlan}/toggle-status', [MaintenancePlanController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{maintenancePlan}/create-order', [MaintenanceOrderController::class, 'storeFromPlan'])->name('create-order');
    });

    // 保養工單（maintenance_orders）— 第 1 週任務 4、5、6
    Route::prefix('maintenance-orders')->name('maintenance-orders.')->group(function () {
        Route::get('/', [MaintenanceOrderController::class, 'index'])->name('index');
        Route::get('/{maintenanceOrder}', [MaintenanceOrderController::class, 'show'])->name('show');

        // 保養結果（maintenance_results）— 第 2 週任務，OK/NG 回報與 NG 轉報修
        Route::get('/{maintenanceOrder}/result/create', [MaintenanceResultController::class, 'create'])->name('results.create');
        Route::post('/{maintenanceOrder}/result', [MaintenanceResultController::class, 'store'])->name('results.store');
    });

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
});
