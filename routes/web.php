<?php

use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\RepairLogController;
use App\Http\Controllers\RepairRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// feature/repair（彭仕衡）：自助知識庫 + 報修 + 維修主流程。
Route::resource('knowledge-base', KnowledgeBaseController::class);

// 「問題已解決」最小流程：只做跳轉並顯示感謝訊息，不記錄額外狀態。
Route::get('knowledge-base/{knowledge_base}/resolved', [KnowledgeBaseController::class, 'resolved'])
    ->name('knowledge-base.resolved');

// 本週不做編輯、刪除報修單本身，狀態改變一律走下面的 assign/start/repair-logs 動作路由。
Route::resource('repair-requests', RepairRequestController::class)
    ->only(['index', 'create', 'store', 'show']);

// 人工派工（新報修 -> 已派工）與開始處理（已派工 -> 處理中）。
Route::post('repair-requests/{repair_request}/assign', [RepairRequestController::class, 'assign'])
    ->name('repair-requests.assign');
Route::post('repair-requests/{repair_request}/start', [RepairRequestController::class, 'start'])
    ->name('repair-requests.start');

// 重新指派（依《第四週個人工作計畫》第 1 項）：換維修人員/處理日期，不改變案件狀態。
Route::post('repair-requests/{repair_request}/reassign', [RepairRequestController::class, 'reassign'])
    ->name('repair-requests.reassign');

// 維修填單，送出後自動把案件推進到「待驗收」。
Route::get('repair-requests/{repair_request}/repair-logs/create', [RepairLogController::class, 'create'])
    ->name('repair-logs.create');
Route::post('repair-requests/{repair_request}/repair-logs', [RepairLogController::class, 'store'])
    ->name('repair-logs.store');

// 驗收通過結案 / 驗收不通過退回重修（依《第三週個人工作計畫》第 2、3 項）。
Route::post('repair-requests/{repair_request}/complete', [RepairRequestController::class, 'complete'])
    ->name('repair-requests.complete');
Route::post('repair-requests/{repair_request}/reject', [RepairRequestController::class, 'reject'])
    ->name('repair-requests.reject');
