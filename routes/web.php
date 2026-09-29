<?php

use App\Http\Controllers\MaintenanceItemController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenancePlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 保養項目（maintenance_items）— 第 1 週任務 1
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
});
