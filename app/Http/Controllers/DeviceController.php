<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;                // 報修單狀態列舉（算「進行中的報修單」時要排除已結案）
use App\Exports\TableExport;
use App\Models\Classroom;                         // 教室資料表的模型
use App\Models\Device;                            // 設備資料表的模型
use App\Models\DeviceCategory;                    // 設備類別資料表的模型
use App\Services\AuditLogger;                     // 共用的操作紀錄寫入工具
use App\Services\DeviceStatusService;             // 統一處理「設備狀態變更」的服務（會同步教室異常旗標與操作紀錄）
use Illuminate\Http\Request;                      // 這一次瀏覽器送來的請求
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;                   // 自訂 exists 規則（排除軟刪除資料）用
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
    /** QR 貼紙一次最多印幾張（A4 每頁 24 張，10 頁）。 */
    private const QR_SHEET_LIMIT = 240;

    /** 設備列表（GET /devices）：可依關鍵字、教室、類別、狀態、是否異常篩選，每頁 15 筆。主控台的圖表點擊與教室主檔的連結也會帶參數過來。 */
    public function index(Request $request)
    {
        $user = $request->user();

        // with(...)：順便查好類別與教室，畫面顯示名稱時不用每台設備再多查一次。
        $devices = Device::with(['category', 'classroom'])
            // withCount：算出每台設備「進行中的報修單」數（不含已結案，且只算這位用戶看得到的案件），
            // 列表上顯示並連到報修看板（?device_id=）。
            ->withCount(['repairRequests as open_repairs_count' => fn ($repairs) => $repairs
                ->visibleTo($user)
                ->where('status', '!=', RepairRequestStatus::Completed->value)])
            // 近 N 天報修次數（不分狀態、不分誰看得到，因為這是在看「這台設備本身」的故障頻率，
            // 不是在看「這個用戶能不能看」）：達到 Device::REPEAT_REPAIR_THRESHOLD 就是「重複報修」。
            ->withCount(['repairRequests as recent_repairs_count' => fn ($repairs) => $repairs
                ->where('created_at', '>=', now()->subDays(Device::REPEAT_REPAIR_DAYS))])
            ->tap(fn ($query) => $this->applyFilters($query, $request))
            ->orderBy('device_code')   // 依設備編號排序
            ->paginate(15)
            ->withQueryString();       // 換頁時保留篩選條件

        // 篩選列的下拉選單：所有教室、所有設備類別。
        $classrooms = Classroom::orderBy('classroom_code')->get();
        $categories = DeviceCategory::orderBy('name')->get();

        $chart = \App\Services\MasterCharts::deviceStatus();   // 頁首「統計圖表」按鈕的圖表資料
        return view('devices.index', compact('devices', 'classrooms', 'categories', 'chart'));
    }

    /**
     * 設備列表與「QR 貼紙列印」共用的篩選條件（關鍵字、教室、類別、狀態、只看異常），
     * 這樣「列印」按下去印的就是畫面上篩出來的那些設備。
     */
    private function applyFilters($query, Request $request): void
    {
        $query
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
            // ?abnormal=1 只列出異常設備（維修中、已淘汰、停用）；教室主檔的「異常設備」數字會連到這裡。
            ->when($request->boolean('abnormal'), fn ($query) => $query->abnormal());
    }

    /** 顯示新增設備表單（GET /devices/create）。 */
    public function create()
    {
        $categories = DeviceCategory::orderBy('name')->get();
        // 新增設備只能指到啟用中的教室，停用教室不該再被分配新設備
        $classrooms = Classroom::where('is_active', true)->orderBy('classroom_code')->get();

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

        return redirect()->route('devices.index')->with('success', __('devices.flash.created'));
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
        // 編輯時仍要能看到設備目前所在的教室，就算那間教室後來被停用了，
        // 否則下拉選單選不到目前值，表單一儲存就會把設備搬到別間教室
        $classrooms = Classroom::where('is_active', true)
            ->orWhere('id', $device->classroom_id)
            ->orderBy('classroom_code')
            ->get();

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

        // 還有沒結案的報修單時，不能把狀態從「維修中／已淘汰／停用」直接改回「正常」（要先把報修單處理完）。
        if ($newStatus === 'normal' && $device->status !== 'normal'
            && $device->repairRequests()->where('status', '!=', RepairRequestStatus::Completed->value)->exists()) {
            return back()->withInput()->withErrors(['status' => '這台設備還有沒結案的報修單，不能直接改成「正常」。請先完成報修單的驗收。']);
        }

        // 記住修改前的教室：如果這次把設備搬到別間，舊教室也要重新計算異常標記。
        $oldClassroom = $device->classroom;

        $device->update($data);   // 先更新一般欄位（編號、品牌、教室...）
        AuditLogger::log('updated', $device, $data);

        // 狀態與核心旗標一律透過統一服務寫入，才會觸發 audit log 與教室異常旗標同步
        DeviceStatusService::updateStatus($device, $newStatus);
        DeviceStatusService::setCore($device, $newIsCore);

        // 設備換了教室：舊教室少了一台設備，要重新檢查它是不是還有異常設備；
        // 新教室多了一台設備（可能是壞掉的核心設備），也要重新檢查，不然新教室不會被標成異常。
        if ($oldClassroom && $oldClassroom->id !== $device->classroom_id) {
            DeviceStatusService::syncClassroom($oldClassroom);
            DeviceStatusService::syncClassroom(Classroom::find($device->classroom_id));
        }

        return redirect()->route('devices.index')->with('success', __('devices.flash.updated'));
    }

    /** 停用設備（PATCH /devices/{device}/disable）：狀態改成 disabled 並軟刪除（資料保留，不再出現在列表）。 */
    public function disable(Device $device)
    {
        DeviceStatusService::updateStatus($device, 'disabled', '設備停用');
        $classroom = $device->classroom;   // 先記住教室，因為刪除後還要用它重新計算異常標記
        $device->delete();
        DeviceStatusService::syncClassroom($classroom);

        return redirect()->route('devices.index')->with('success', __('devices.flash.disabled'));
    }

    /**
     * 匯出設備清單（GET /devices/export）：依目前的篩選條件輸出 Excel，單次最多 5000 筆，並寫入操作紀錄。
     */
    public function export(Request $request)
    {
        $query = Device::with(['category', 'classroom'])->orderBy('device_code');
        $this->applyFilters($query, $request);

        $rows = $query->limit(5000)->get()->map(fn (Device $device) => [
            $device->device_code, $device->asset_code, $device->category?->name, $device->brand, $device->model, $device->serial_number,
            $device->classroom?->classroom_code, $device->classroom?->name,
            Device::statusLabel($device->status), $device->is_core ? '是' : '否',
            $device->warranty_end_date?->format('Y-m-d'), $device->created_at?->format('Y-m-d H:i'),
        ])->all();

        $file = '設備清單-' . now()->format('Ymd-His') . '.xlsx';
        AuditLogger::log('downloaded', null, ['file' => $file, 'rows' => count($rows)], "匯出設備清單 {$file}（" . count($rows) . ' 筆）');

        return Excel::download(new TableExport('設備', ['設備編號', '資產編號', '類別', '品牌', '型號', '序號', '教室代碼', '教室', '狀態', '核心設備', '保固到期日', '建立時間'], $rows), $file);
    }

    /**
     * QR 貼紙批次列印（GET /devices/qr-sheet）：依目前的篩選條件，一次輸出可直接列印的 A4 貼紙頁。
     * 每張貼紙有 QR Code（指向設備固定入口網址）、設備編號、類別與品牌型號、所在教室。
     * 一次最多印 QR_SHEET_LIMIT 張，超過會提示「請加上篩選條件分批印」。
     */
    public function qrSheet(Request $request)
    {
        $query = Device::with(['category', 'classroom'])->orderBy('device_code');
        $this->applyFilters($query, $request);

        $total = (clone $query)->count();
        $labels = $query->limit(self::QR_SHEET_LIMIT)->get()->map(fn (Device $device) => [
            'device' => $device,
            // 內嵌 SVG：不用另外載入圖片，列印時一次到位。
            'svg' => (string) QrCode::format('svg')->size(220)->margin(0)->generate(route('devices.entry', $device)),
        ]);

        AuditLogger::log('downloaded', null, ['type' => 'device_qr_sheet', 'count' => $labels->count()], '列印設備 QR 貼紙 ' . $labels->count() . ' 張');

        return view('devices.qr-sheet', ['labels' => $labels, 'total' => $total, 'limit' => self::QR_SHEET_LIMIT]);
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
            'warranty_end_date' => ['nullable', 'date'],                                 // 保固到期日
            // 教室不能指到已被軟刪除的教室，exists 預設不看 deleted_at
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->whereNull('deleted_at')],
            // 狀態只能是 Device::STATUSES 列出的幾種（normal / repairing / retired / disabled）。
            'status' => ['required', 'in:' . implode(',', Device::STATUSES)],
            'is_core' => ['sometimes', 'boolean'],                                    // 是否為核心設備；sometimes = 有送才檢查
        ], [
            // 已停用（軟刪除）的設備編號仍被占用，要讓人知道原因。
            'device_code.unique' => '這個設備編號已經被使用（可能是已停用的設備，停用的設備編號不能重複使用）。',
        ]);
    }
}
