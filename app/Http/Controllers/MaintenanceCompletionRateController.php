<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceCompletionRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 保養完成率資料接口（第 3 週任務 4）。
 *
 * 這不是給使用者看的頁面，是給劉家芸的 Dashboard 模組呼叫的唯讀 JSON 接口，
 * 在他的分支併進 develop 之前，先讓他能用這個網址直接驗證資料格式；
 * 併進來之後他也可以選擇直接 inject MaintenanceCompletionRateService，不一定要走 HTTP。
 *
 * 參數（全部選填）：
 *   from, to：YYYY-MM-DD，預設本月
 *   device_id：只看特定設備
 */
class MaintenanceCompletionRateController extends Controller
{
    public function index(Request $request, MaintenanceCompletionRateService $service): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : null;
        $deviceId = $request->filled('device_id') ? $request->integer('device_id') : null;

        return response()->json($service->forPeriod($from, $to, $deviceId));
    }
}
