<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\DeviceCategory;   // 設備類別資料表的模型
use App\Services\AuditLogger;    // 共用的操作紀錄寫入工具
use App\Support\StrokeOrder;
use Illuminate\Http\Request;                    // 這一次瀏覽器送來的請求
use Illuminate\Pagination\LengthAwarePaginator;  // 自己排序後手動分頁

/**
 * 設備類別主檔（投影機、電腦、冷氣...）：列表／搜尋、新增、編輯、刪除。
 * 需要 device-categories.manage 權限（見 routes/web.php）。
 * 刪除規則：還有設備使用這個類別時不能刪除（已停用的設備也算：它們只是從列表隱藏，資料還指向這個類別）。
 * 平常是從設備主檔右上角的「新增類別」在對話框裡打開列表（?modal=1）：新增、刪除後留在對話框裡（網址保留 modal=1）。
 *
 * 【Controller 基本觀念】請先看 UserController.php 檔頭；每個 public 方法對應一個網址動作。
 */
class DeviceCategoryController extends Controller
{
    /** 類別列表（GET /device-categories）：可用名稱關鍵字搜尋，每頁 15 筆。 */
    public function index(Request $request)
    {
        // withCount('devices')：順便算出每個類別底下有幾台設備，列表上顯示用。
        // 依名稱筆畫排序（筆畫少的在前，見 App\Support\StrokeOrder）：資料庫不會依筆畫排，所以撈出來排好再自己分頁。
        $sorted = StrokeOrder::sort(DeviceCategory::withCount('devices')
            // 有填關鍵字才套用：名稱包含關鍵字（like %字%）。
            ->when($request->filled('keyword'), fn ($query) => $query->where('name', 'like', '%' . $request->string('keyword') . '%'))
            ->get());
        $page = LengthAwarePaginator::resolveCurrentPage();
        $categories = (new LengthAwarePaginator($sorted->forPage($page, 15)->values(), $sorted->count(), 15, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();   // 換頁時保留搜尋條件

        $chart = \App\Services\MasterCharts::deviceCategoryRepairRanking($request->user());   // 頁首「統計圖表」按鈕的圖表資料
        $categoryNames = StrokeOrder::sort(DeviceCategory::pluck('name'), fn ($name) => $name);   // 篩選欄位的下拉選項（可以輸入，也可以直接選）
        return view('device-categories.index', compact('categories', 'chart', 'categoryNames'));
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
        ], [
            'name.required' => '請填寫類別名稱。',
            'name.unique' => '這個類別名稱已經存在了，請換一個。',
            'name.max' => '類別名稱長度超過上限（255 字）。',
        ]);

        $category = DeviceCategory::create($data);

        AuditLogger::log('created', $category, $data);

        return redirect()->route('device-categories.index', $this->modalQuery($request))->with('success', __('device_categories.flash.created'));
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
        ], [
            'name.required' => '請填寫類別名稱。',
            'name.unique' => '這個類別名稱已經存在了，請換一個。',
            'name.max' => '類別名稱長度超過上限（255 字）。',
        ]);

        $deviceCategory->update($data);

        AuditLogger::log('updated', $deviceCategory, $data);

        return redirect()->route('device-categories.index')->with('success', __('device_categories.flash.updated'));
    }

    /** 刪除類別（DELETE /device-categories/{deviceCategory}）：還有設備在用就擋下。 */
    public function destroy(Request $request, DeviceCategory $deviceCategory)
    {
        $back = $this->modalQuery($request);

        // exists()：只問「有沒有」，不需要真的把設備全部撈出來。
        if ($deviceCategory->devices()->exists()) {
            return $this->backToList('device-categories.index', $back)->with('error', __('device_categories.errors.in_use'));
        }
        // 已停用的設備（軟刪除）不算「使用中」，但資料仍指向這個類別，刪掉會違反外鍵，所以也擋下並說明。
        if ($deviceCategory->devices()->onlyTrashed()->exists()) {
            return $this->backToList('device-categories.index', $back)->with('error', __('device_categories.errors.has_disabled_devices'));
        }

        $deviceCategory->delete();

        AuditLogger::log('deleted', $deviceCategory);

        return $this->backToList('device-categories.index', $back)->with('success', __('device_categories.flash.deleted'));
    }

    /** 在對話框裡操作（?modal=1）時，回列表也帶著 modal=1，結果留在對話框裡顯示。 */
    private function modalQuery(Request $request): array
    {
        return $request->boolean('modal') ? ['modal' => 1] : [];
    }
}
