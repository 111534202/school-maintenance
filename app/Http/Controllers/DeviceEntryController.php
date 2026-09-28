<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Support\Facades\Route;

/**
 * QR / URL 掃描後看到的設備入口頁，任何登入角色都能看，不限管理端。
 * 只依 device_code 查目前最新資料，不在 QR 裡寫死教室等會變動的內容。
 */
class DeviceEntryController extends Controller
{
    public function show(Device $device)
    {
        $device->load(['category', 'classroom.department']);

        // 報修/知識庫路由由彭仕衡的模組提供，這裡先用 Route::has() 保護，
        // 等他的路由合併進來就會自動生效，不用回來改這支 controller。
        $repairRouteName = collect(['repairs.create', 'repair-requests.create'])
            ->first(fn ($name) => Route::has($name));

        $kbRouteName = collect(['kb.device', 'knowledge-base.device'])
            ->first(fn ($name) => Route::has($name));

        $repairUrl = $repairRouteName ? route($repairRouteName, ['device' => $device->id]) : null;
        $kbUrl = $kbRouteName ? route($kbRouteName, ['device' => $device->id]) : null;

        return view('devices.entry', compact('device', 'repairUrl', 'kbUrl'));
    }
}
