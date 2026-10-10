<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DeviceCategoryController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceEntryController;
use App\Http\Controllers\PartController;
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

    // 管理端入口：教室、設備類別、設備管理、audit log 查詢僅開放 admin / it_manager
    Route::middleware('role:admin,it_manager')->group(function () {
        Route::resource('parts', PartController::class);
    
        Route::resource('classrooms', ClassroomController::class)->except(['show', 'destroy']);
        Route::patch('classrooms/{classroom}/toggle', [ClassroomController::class, 'toggle'])->name('classrooms.toggle');

        Route::resource('device-categories', DeviceCategoryController::class)->except(['show']);

        Route::resource('devices', DeviceController::class)->except(['destroy']);
        Route::patch('devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable');
        Route::get('devices/{device}/qrcode', [DeviceController::class, 'qrcode'])->name('devices.qrcode');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
