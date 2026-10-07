<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AI 預防保養參數設定（第 4 週任務 3）。路由已限定 admin / it_manager。
 */
class AiSettingController extends Controller
{
    public function edit(): View
    {
        return view('ai_settings.edit', ['settings' => AiSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'risk_threshold' => ['required', 'numeric', 'min:0.05', 'max:1'],
            'min_completed_orders' => ['required', 'integer', 'min:1', 'max:24'],
            'recent_window' => ['required', 'integer', 'min:2', 'max:24'],
            'dedup_window_days' => ['required', 'integer', 'min:0', 'max:180'],
            'require_approval' => ['required', 'boolean'],
        ]);

        $before = AiSetting::current();

        AiSetting::put(AiSetting::RISK_THRESHOLD, (float) $data['risk_threshold']);
        AiSetting::put(AiSetting::MIN_COMPLETED_ORDERS, (int) $data['min_completed_orders']);
        AiSetting::put(AiSetting::RECENT_WINDOW, (int) $data['recent_window']);
        AiSetting::put(AiSetting::DEDUP_WINDOW_DAYS, (int) $data['dedup_window_days']);
        AiSetting::put(AiSetting::REQUIRE_APPROVAL, (bool) $data['require_approval']);

        AuditLogger::log('ai_settings_updated', null, ['from' => $before, 'to' => AiSetting::current()], '調整 AI 預防保養參數');

        return redirect()->route('ai-settings.edit')->with('success', 'AI 預防保養設定已更新。');
    }
}
