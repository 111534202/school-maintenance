<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\DeviceCategory;   // 設備類別資料表的模型
use App\Services\AuditLogger;    // 共用的操作紀錄寫入工具
use Illuminate\Http\Request;     // 這一次瀏覽器送來的請求

/**
 * 設備類別主檔（投影機、電腦、冷氣...）：列表／搜尋、新增、編輯、刪除。
 * 需要 device-categories.manage 權限（見 routes/web.php）。
 * 刪除規則：還有設備使用這個類別時不能刪除。
 *
 * 【Controller 基本觀念】請先看 UserController.php 檔頭；每個 public 方法對應一個網址動作。
 */
class DeviceCategoryController extends Controller
{
    /** 類別列表（GET /device-categories）：可用名稱關鍵字搜尋，每頁 15 筆。 */
    public function index(Request $request)
    {
        // withCount('devices')：順便算出每個類別底下有幾台設備，列表上顯示用。
        $categories = DeviceCategory::withCount('devices')
            // 有填關鍵字才套用：名稱包含關鍵字（like %字%）。
            ->when($request->filled('keyword'), fn ($query) => $query->where('name', 'like', '%' . $request->string('keyword') . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();   // 換頁時保留搜尋條件

        return view('device-categories.index', compact('categories'));
    }

    /** 顯示新增類別表單（GET /device-categories/create）。 */
    public function create()
    {
        return view('device-categories.create');
    }

    /** 接收新增表單（POST /device-categories）。 */
    public function store(Request $request)
    {
        // 名稱必填、最長 255 字、不可與其他類別重複；驗證不過會自動導回表單並顯示錯誤。
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:device_categories,name'],
        ]);

        $category = DeviceCategory::create($data);

        AuditLogger::log('created', $category, $data);

        return redirect()->route('device-categories.index')->with('success', '設備類別已新增。');
    }

    /** 顯示編輯表單（GET /device-categories/{deviceCategory}/edit）。 */
    public function edit(DeviceCategory $deviceCategory)
    {
        // 畫面裡用的變數名稱是 category，所以這裡改名傳進去。
        return view('device-categories.edit', ['category' => $deviceCategory]);
    }

    /** 接收編輯表單（PUT /device-categories/{deviceCategory}）。 */
    public function update(Request $request, DeviceCategory $deviceCategory)
    {
        // 規則最後面接自己的編號，代表「名稱不可重複，但可以跟自己現在的名稱一樣」。
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:device_categories,name,' . $deviceCategory->id],
        ]);

        $deviceCategory->update($data);

        AuditLogger::log('updated', $deviceCategory, $data);

        return redirect()->route('device-categories.index')->with('success', '設備類別已更新。');
    }

    /** 刪除類別（DELETE /device-categories/{deviceCategory}）：還有設備在用就擋下。 */
    public function destroy(DeviceCategory $deviceCategory)
    {
        // exists()：只問「有沒有」，不需要真的把設備全部撈出來。
        if ($deviceCategory->devices()->exists()) {
            return redirect()->route('device-categories.index')->with('error', '此類別仍有設備使用中，無法刪除。');
        }

        $deviceCategory->delete();

        AuditLogger::log('deleted', $deviceCategory);

        return redirect()->route('device-categories.index')->with('success', '設備類別已刪除。');
    }
}
