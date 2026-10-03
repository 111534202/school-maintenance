<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Classroom;                         // 教室資料表的模型
use App\Models\Device;                            // 設備資料表的模型
use App\Models\DeviceCategory;                    // 設備類別資料表的模型
use App\Services\AuditLogger;                     // 共用的操作紀錄寫入工具
use App\Services\DeviceStatusService;             // 統一處理「設備狀態變更」的服務（會同步教室異常旗標與操作紀錄）
use Illuminate\Http\Request;                      // 這一次瀏覽器送來的請求
use SimpleSoftwareIO\QrCode\Facades\QrCode;       // 產生 QR Code 圖片的套件

/**
 * 設備主檔：列表／篩選、新增、檢視、編輯、停用、產生 QR Code（需要 devices.manage 權限，見 routes/web.php）。
 *
 * 重點觀念：設備的「狀態」與「核心設備」旗標不要直接改資料表欄位，
 * 一律透過 DeviceStatusService，這樣才會自動寫操作紀錄、並更新教室的「設備異常」標記。
 *
 * 【Controller 基本觀念】請先看 UserController.php 檔頭；每個 public 方法對應一個網址動作。
 * 【想新增設備欄位】migration 加欄位 → Models/Device.php 的 $fillable → 本檔 validated() 加規則
 *   → resources/views/devices/_form.blade.php 加輸入框、show.blade.php 加顯示。
 */
class DeviceController extends Controller
{
    /** 設備列表（GET /devices）：可依關鍵字、教室、類別、狀態篩選，每頁 15 筆。主控台的圖表點擊也會帶 status 參數過來。 */
    public function index(Request $request)
    {
        // with(...)：順便查好類別與教室，畫面顯示名稱時不用每台設備再多查一次。
        $devices = Device::with(['category', 'classroom'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword');
                // 設備編號、資產編號、品牌、型號任一個包含關鍵字就算符合；括號包起來避免「或」影響其他篩選。
                $query->where(function ($q) use ($keyword) {
                    $q->where('device_code', 'like', "%{$keyword}%")
                      ->orWhere('asset_code', 'like', "%{$keyword}%")
                      ->orWhere('brand', 'like', "%{$keyword}%")
                      ->orWhere('model', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('classroom_id'), fn ($query) => $query->where('classroom_id', $request->integer('classroom_id')))
            ->when($request->filled('device_category_id'), fn ($query) => $query->where('device_category_id', $request->integer('device_category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('device_code')   // 依設備編號排序
            ->paginate(15)
            ->withQueryString();       // 換頁時保留篩選條件

        // 篩選列的下拉選單：所有教室、所有設備類別。
        $classrooms = Classroom::orderBy('room_code')->get();
        $categories = DeviceCategory::orderBy('name')->get();

        return view('devices.index', compact('devices', 'classrooms', 'categories'));
    }

    /** 顯示新增設備表單（GET /devices/create）。 */
    public function create()
    {
        $categories = DeviceCategory::orderBy('name')->get();
        $classrooms = Classroom::orderBy('room_code')->get();

        return view('devices.create', compact('categories', 'classrooms'));
    }

    /** 接收新增表單（POST /devices）。 */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        // 核取方塊沒勾的時候瀏覽器根本不會送這個欄位，所以用 boolean() 明確轉成 true / false。
        $data['is_core'] = $request->boolean('is_core');

        $device = Device::create($data);

        AuditLogger::log('created', $device, $data);
        // 新設備可能一開始就是異常狀態，所以要重新計算它所在教室的「設備異常」標記。
        DeviceStatusService::syncClassroom($device->classroom);

        return redirect()->route('devices.index')->with('success', '設備已新增。');
    }

    /** 檢視單一設備（GET /devices/{device}）；Device $device 由網址上的編號自動帶入。 */
    public function show(Device $device)
    {
        // load：補查關聯資料（類別、教室，以及教室所屬的部門）。
        $device->load(['category', 'classroom.department']);

        return view('devices.show', compact('device'));
    }

    /** 顯示編輯表單（GET /devices/{device}/edit）。 */
    public function edit(Device $device)
    {
        $categories = DeviceCategory::orderBy('name')->get();
        $classrooms = Classroom::orderBy('room_code')->get();

        return view('devices.edit', compact('device', 'categories', 'classrooms'));
    }

    /** 接收編輯表單（PUT /devices/{device}）。 */
    public function update(Request $request, Device $device)
    {
        $data = $this->validated($request, $device->id);
        // 先把「狀態」與「核心設備」從一般資料拿出來，因為它們要走專用的服務寫入（見下面）。
        $newStatus = $data['status'];
        $newIsCore = $request->boolean('is_core');
        unset($data['status'], $data['is_core']);

        // 記住修改前的教室：如果這次把設備搬到別間，舊教室也要重新計算異常標記。
        $oldClassroom = $device->classroom;

        $device->update($data);   // 先更新一般欄位（編號、品牌、教室...）
        AuditLogger::log('updated', $device, $data);

        // 狀態與核心旗標一律透過統一服務寫入，才會觸發 audit log 與教室異常旗標同步
        DeviceStatusService::updateStatus($device, $newStatus);
        DeviceStatusService::setCore($device, $newIsCore);

        // 設備換了教室：舊教室少了一台設備，要重新檢查它是不是還有異常設備。
        if ($oldClassroom && $oldClassroom->id !== $device->classroom_id) {
            DeviceStatusService::syncClassroom($oldClassroom);
        }

        return redirect()->route('devices.index')->with('success', '設備已更新。');
    }

    /** 停用設備（PATCH /devices/{device}/disable）：狀態改成 disabled 並軟刪除（資料保留，不再出現在列表）。 */
    public function disable(Device $device)
    {
        DeviceStatusService::updateStatus($device, 'disabled', '設備停用');
        $classroom = $device->classroom;   // 先記住教室，因為刪除後還要用它重新計算異常標記
        $device->delete();
        DeviceStatusService::syncClassroom($classroom);

        return redirect()->route('devices.index')->with('success', '設備已停用。');
    }

    /**
     * QR Code 只編碼設備識別碼指向的固定入口網址（devices.entry），
     * 不寫死教室/類別等會變動的資料，掃碼當下即時查詢最新狀態。
     */
    public function qrcode(Device $device)
    {
        // 這個設備的固定入口網址，例如 http://網站/d/DEV-001。
        $url = route('devices.entry', $device);

        // 產生 300px 的 SVG（向量圖，放大列印也不會模糊）並以圖片格式回傳給瀏覽器。
        return response(QrCode::format('svg')->size(300)->generate($url))
            ->header('Content-Type', 'image/svg+xml');
    }

    /**
     * 新增／編輯共用的驗證規則（想改欄位限制就改這裡）。
     * $ignoreId：編輯時傳入自己的編號，讓「設備編號不可重複」的檢查排除自己。
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            // 設備編號：必填、不可重複（QR Code 網址用它，所以一定要唯一）。
            'device_code' => ['required', 'string', 'max:255', 'unique:devices,device_code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'asset_code' => ['nullable', 'string', 'max:255'],                        // 資產編號（學校財產編號），可不填
            'device_category_id' => ['required', 'exists:device_categories,id'],      // 設備類別：必須是真的存在的類別
            'brand' => ['nullable', 'string', 'max:255'],                             // 品牌
            'model' => ['nullable', 'string', 'max:255'],                             // 型號
            'serial_number' => ['nullable', 'string', 'max:255'],                     // 序號
            'warranty_until' => ['nullable', 'date'],                                 // 保固到期日
            'classroom_id' => ['required', 'exists:classrooms,id'],                   // 所在教室：必須是真的存在的教室
            // 狀態只能是 Device::STATUSES 列出的幾種（normal / repairing / retired / disabled）。
            'status' => ['required', 'in:' . implode(',', Device::STATUSES)],
            'is_core' => ['sometimes', 'boolean'],                                    // 是否為核心設備；sometimes = 有送才檢查
        ]);
    }
}
