<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Device;                      // 設備資料表的模型
use Illuminate\Support\Facades\Route;       // 用來問「某個路由名稱存不存在」

/**
 * QR / URL 掃描後看到的設備入口頁，任何登入角色都能看，不限管理端。
 * 只依 device_code 查目前最新資料，不在 QR 裡寫死教室等會變動的內容。
 *
 * 入口頁上會有「我要報修」「查看自助知識庫」按鈕，這兩個功能由別的模組提供，
 * 所以這裡用 Route::has() 檢查路由存在才顯示按鈕，沒有的話按鈕自動不顯示，不會報錯。
 */
class DeviceEntryController extends Controller
{
    /** 設備入口頁（GET /d/{device_code}）。Device $device 由網址上的設備編號自動找出來（見 routes/web.php）。 */
    public function show(Device $device)
    {
        // 補查類別、教室、教室所屬部門，畫面要顯示。
        $device->load(['category', 'classroom.department']);

        // 報修/知識庫路由由彭仕衡的模組提供，這裡先用 Route::has() 保護，
        // 等他的路由合併進來就會自動生效，不用回來改這支 controller。
        // 做法：列出可能的路由名稱，取第一個「真的存在」的；一個都沒有就是 null。
        $repairRouteName = collect(['repairs.create', 'repair-requests.create'])
            ->first(fn ($name) => Route::has($name));

        $kbRouteName = collect(['kb.device', 'knowledge-base.device'])
            ->first(fn ($name) => Route::has($name));

        // 有找到路由就組出網址（並帶上設備編號讓對方頁面自動帶入設備）；沒有就是 null，畫面就不顯示該按鈕。
        $repairUrl = $repairRouteName ? route($repairRouteName, ['device' => $device->id]) : null;
        $kbUrl = $kbRouteName ? route($kbRouteName, ['device' => $device->id]) : null;

        return view('devices.entry', compact('device', 'repairUrl', 'kbUrl'));
    }
}
