<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;                // 報修單狀態列舉（算「進行中的報修單」時要排除已結案）
use App\Http\Controllers\Concerns\ExportsList;      // 列表「匯出 Excel」共用流程
use App\Support\DeviceExportColumns;             // 設備清單匯出的欄位
use App\Models\Classroom;                         // 教室資料表的模型
use App\Models\Device;                            // 設備資料表的模型
use App\Support\EquipmentCode;                    // 設備編號、零件編號共用的格式與不重複規則
use App\Models\DeviceCategory;                    // 設備類別資料表的模型
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Services\AuditLogger;                     // 共用的操作紀錄寫入工具
use App\Services\DeviceStatusService;             // 統一處理「設備狀態變更」的服務（會同步教室異常旗標與操作紀錄）
use Illuminate\Http\Request;                      // 這一次瀏覽器送來的請求
use Illuminate\Validation\Rule;
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
    use ExportsList;

    /** QR 貼紙一次最多印幾張（A4 每頁 24 張，10 頁）。 */
    private const QR_SHEET_LIMIT = 240;

    /** 設備列表（GET /devices）：可依關鍵字、教室、類別、狀態、是否異常篩選，每頁 15 筆。主控台的圖表點擊與教室主檔的連結也會帶參數過來。 */
    public function index(Request $request)
    {
        $user = $request->user();

        // with(...)：順便查好類別與教室，畫面顯示名稱時不用每台設備再多查一次。
        $devices = Device::with(['category', 'classroom'])
            ->withAwaitingRepair()   // 順便算好是不是「待維修」，列表的狀態徽章才不用每台再查
            // withCount：算出每台設備「進行中的報修單」數（不含已結案、已退回，且只算這位用戶看得到的案件），
            // 列表上顯示並連到報修看板（?device_id=）。
            ->withCount(['repairRequests as open_repairs_count' => fn ($repairs) => $repairs
                ->visibleOnBoard($user)   // 和報修看板同一個範圍，點進去的列表筆數才會一致
                ->open()])                // 未結案（已結案、已退回都不算）
            // 近 N 天報修次數（日期從 N 天前的 00:00 起算；已退回的不算；只算這位用戶看得到的案件），
            // 徽章連到報修看板時帶同樣的條件（這台設備、新增日期、排除已退回），筆數一致。達到 Device::REPEAT_REPAIR_THRESHOLD 就是「重複報修」。
            ->withCount(['repairRequests as recent_repairs_count' => fn ($repairs) => $repairs
                ->visibleOnBoard($user)
                ->where('created_at', '>=', now()->subDays(Device::REPEAT_REPAIR_DAYS)->startOfDay())
                ->where('status', '<>', \App\Enums\RepairRequestStatus::Returned->value)])
            ->tap(fn ($query) => $this->applyFilters($query, $request))
            ->orderBy('device_code')   // 依設備編號排序
            ->paginate(15)
            ->withQueryString();       // 換頁時保留篩選條件

        // 篩選列的下拉選單：所有教室、所有設備類別。
        $classrooms = Classroom::orderBy('classroom_code')->get();
        $categories = \App\Support\StrokeOrder::sort(DeviceCategory::all());   // 依筆畫排序

        $chart = \App\Services\MasterCharts::deviceStatus();   // 頁首「統計圖表」按鈕的圖表資料
        // 「匯出 Excel」預覽視窗要列的設備（目前篩選條件下最多 500 台）。
        $exportDevices = $this->exportQuery($request)->limit(500)->get();
        // 「列印 QR 貼紙」對話框要列的設備（目前篩選條件下，最多一次能印的張數）。
        $qrDevices = $exportDevices->take(self::QR_SHEET_LIMIT);
        $qrLimit = self::QR_SHEET_LIMIT;

        return view('devices.index', compact('devices', 'classrooms', 'categories', 'chart', 'exportDevices', 'qrDevices', 'qrLimit'));
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
            // 狀態篩選照畫面上的狀態：待維修由報修單推算（見 Device::scopeWhereDisplayStatus）。
            ->when($request->filled('status'), fn ($query) => $query->whereDisplayStatus($request->string('status')->toString()))
            // ?abnormal=1 只列出異常設備（有未結束報修單、不含已淘汰）；教室主檔的「異常設備」數字會連到這裡。
            ->when($request->boolean('abnormal'), fn ($query) => $query->abnormal());
    }

    /** 顯示新增設備表單（GET /devices/create）。 */
    public function create()
    {
        $categories = \App\Support\StrokeOrder::sort(DeviceCategory::all());   // 依筆畫排序
        // 新增設備只能指到啟用中的教室，停用教室不該再被分配新設備
        $classrooms = Classroom::where('is_active', true)->orderBy('classroom_code')->get();

        return view('devices.create', compact('categories', 'classrooms'));
    }

    /** 接收新增表單（POST /devices）。 */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $device = Device::create($data);

        AuditLogger::log('created', $device, $data);

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
        // 已淘汰是定案：設備資料整筆唯讀，不能編輯（只能查看；不小心淘汰時由系統管理員「取消淘汰」）。
        if ($device->status === 'retired') {
            return redirect()->route('devices.show', $device)->with('error', '這台設備已淘汰，資料已定案，不能編輯。');
        }

        // 狀態下拉選單要知道這台設備現在是不是「待維修」（有已派工、待驗收的報修單）。
        $device = Device::query()->withAwaitingRepair()->findOrFail($device->id);
        $categories = \App\Support\StrokeOrder::sort(DeviceCategory::all());   // 依筆畫排序
        // 編輯時仍要能看到設備目前所在的教室，就算那間教室後來被停用了，
        // 否則下拉選單選不到目前值，表單一儲存就會把設備搬到別間教室
        $classrooms = Classroom::where('is_active', true)
            ->orWhere('id', $device->classroom_id)
            ->orderBy('classroom_code')
            ->get();

        // 選「已淘汰」時要先確認：告訴使用者這台設備名下有多少進行中的東西會一併中止。
        $retireImpact = [
            'repairs' => $device->repairRequests()->whereIn('status', RepairRequestStatus::openValues())->count(),
            'orders' => MaintenanceOrder::query()->where('device_id', $device->id)->unfinished()->count(),
            'plans' => MaintenancePlan::query()->where('device_id', $device->id)->where('is_active', true)->count(),
        ];

        return view('devices.edit', compact('device', 'categories', 'classrooms', 'retireImpact'));
    }

    /** 接收編輯表單（PUT /devices/{device}）。 */
    public function update(Request $request, Device $device)
    {
        if ($device->status === 'retired') {
            return redirect()->route('devices.show', $device)->with('error', '這台設備已淘汰，資料已定案，不能編輯。');
        }

        $data = $this->validated($request, $device->id);
        // 先把「狀態」從一般資料拿出來，因為它要走專用的服務寫入（見下面）。
        $newStatus = $data['status'];
        unset($data['status']);

        // 還有沒結束的報修單（新報修、已派工、待驗收都算）時，不能把狀態從「已淘汰／停用」改回「正常」（要先把報修單處理完）。
        // 「待維修」的設備在表單上只能選「待維修（維持）」「已淘汰」「停用」，送來的 normal 代表維持原狀，不會被擋。
        if ($newStatus === 'normal' && $device->status !== 'normal' && $device->repairRequests()->open()->exists()) {
            return back()->withInput()->withErrors(['status' => '這台設備還有沒結案的報修單（新報修也算），不能改成「正常」。請先完成或退回報修單。']);
        }

        // 記住修改前的教室：如果這次把設備搬到別間，舊教室也要重新計算異常標記。
        $oldClassroom = $device->classroom;

        $device->update($data);   // 先更新一般欄位（編號、品牌、教室...）
        AuditLogger::log('updated', $device, $data);

        // 狀態一律透過統一服務寫入，才會留下狀態變更的操作紀錄。
        DeviceStatusService::updateStatus($device, $newStatus);

        return redirect()->route('devices.index')->with('success', __('devices.flash.updated'));
    }

    /**
     * 取消淘汰（PATCH /devices/{device}/unretire）：不小心改成「已淘汰」時，只有系統管理員能改回「正常」，留下操作紀錄。
     * 淘汰是定案，一般有設備權限的人不能改回來。
     */
    public function unretire(Request $request, Device $device)
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($device->status !== 'retired') {
            return redirect()->route('devices.show', $device)->with('error', '這台設備不是已淘汰，不需要取消淘汰。');
        }

        DeviceStatusService::updateStatus($device, 'normal', '取消淘汰（系統管理員）');

        return redirect()->route('devices.show', $device)->with('success', '已取消淘汰，設備改回「正常」。');
    }

    /** 停用設備（PATCH /devices/{device}/disable）：狀態改成 disabled 並軟刪除（資料保留，不再出現在列表）。 */
    public function disable(Device $device)
    {
        // 已淘汰是定案：不能再停用（資料唯讀，狀態也不能改成停用）。
        if ($device->status === 'retired') {
            return $this->backToList('devices.index')->with('error', '這台設備已淘汰，資料已定案，不能停用。');
        }

        DeviceStatusService::updateStatus($device, 'disabled', '設備停用');
        $device->delete();

        return $this->backToList('devices.index')->with('success', __('devices.flash.disabled'));
    }

    /**
     * 匯出設備清單（GET /devices/export）：依目前的篩選條件輸出 Excel，單次最多 5000 筆，並寫入操作紀錄。
     * 預覽視窗會帶 columns[]（勾選的欄位）與 ids[]（勾選的設備）；流程見 ExportsList。
     */
    public function export(Request $request)
    {
        return $this->exportList($request, $this->exportQuery($request), DeviceExportColumns::class, '設備清單', '設備清單-', '設備', 5000);
    }

    /** 匯出與預覽共用的查詢：目前的篩選條件，依設備編號排序。 */
    private function exportQuery(Request $request)
    {
        $query = Device::with(['category', 'classroom'])->withAwaitingRepair()->orderBy('device_code');
        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * QR 貼紙批次列印（GET /devices/qr-sheet）：依目前的篩選條件，一次輸出可直接列印的 A4 貼紙頁。
     * 每張貼紙有 QR Code（指向設備固定入口網址）、設備編號、類別與品牌型號、所在教室。
     * 一次最多印 QR_SHEET_LIMIT 張，超過會提示「請加上篩選條件分批印」。
     */
    /**
     * 即時檢查設備編號有沒有被用過（新增／修改設備時，輸入編號就檢查，跟零件編號的檢查一樣）。
     * 回傳 JSON：available（能不能用）與 message（顯示在輸入框下面的文字）。修改時帶 ignore=自己的 id，排除自己。
     * 規則跟零件編號共用（App\\Support\\EquipmentCode）：格式不對、和設備或零件編號重複都不能用；已停用的設備編號也算用過。
     */
    public function checkCode(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $ignoreId = $request->integer('ignore') ?: null;

        if ($code === '') {
            return response()->json(['available' => false, 'message' => '請填寫設備編號。']);
        }
        if (! EquipmentCode::validFormat($code) || in_array($code, EquipmentCode::RESERVED, true)) {
            return response()->json(['available' => false, 'message' => EquipmentCode::formatMessage('設備編號')]);
        }

        $conflict = EquipmentCode::conflict($code, ignoreDeviceId: $ignoreId);

        return $conflict
            ? response()->json(['available' => false, 'message' => $conflict])
            : response()->json(['available' => true, 'message' => "設備編號「{$code}」可以使用。"]);
    }

    public function qrSheet(Request $request)
    {
        $query = Device::with(['category', 'classroom'])->orderBy('device_code');
        $this->applyFilters($query, $request);
        // 從「列印 QR 貼紙」對話框勾選的設備（ids[]）：只印勾選的；沒帶就印目前篩選條件下的全部（最多 QR_SHEET_LIMIT 張）。
        $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['integer']]);
        if ($request->filled('ids')) {
            $query->whereIn('devices.id', $request->input('ids'));
        }

        // 已淘汰的設備不再使用，不印 QR 貼紙（記下排除了幾台，畫面上會說明）。
        $excludedRetired = (clone $query)->where('status', 'retired')->count();
        $query->where('status', '!=', 'retired');

        $total = (clone $query)->count();
        $labels = $query->limit(self::QR_SHEET_LIMIT)->get()->map(fn (Device $device) => [
            'device' => $device,
            // 內嵌 SVG：不用另外載入圖片，列印時一次到位。
            'svg' => (string) QrCode::format('svg')->size(220)->margin(0)->generate(route('devices.entry', $device)),
        ]);

        AuditLogger::log('downloaded', null, ['type' => 'device_qr_sheet', 'count' => $labels->count()], '列印設備 QR 貼紙 ' . $labels->count() . ' 張');

        return view('devices.qr-sheet', ['labels' => $labels, 'total' => $total, 'limit' => self::QR_SHEET_LIMIT, 'excludedRetired' => $excludedRetired]);
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
            // 設備編號：必填；跟零件編號同一套規則（App\Support\EquipmentCode）：限英文字母、數字、- _ .，最多 20 個字，
            // 和其他設備編號、零件編號都不能重複（大小寫視為相同、已停用的設備也算）。QR Code 網址用它，所以一定要唯一。
            'device_code' => [
                'required', 'string', 'max:' . EquipmentCode::MAX, 'regex:' . EquipmentCode::PATTERN,
                'unique:devices,device_code' . ($ignoreId ? ",{$ignoreId}" : ''),
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
                    if (in_array((string) $value, EquipmentCode::RESERVED, true)) {
                        $fail('這個設備編號是系統保留的名稱，請換一個。');
                    } elseif ($conflict = EquipmentCode::conflict((string) $value, ignoreDeviceId: $ignoreId)) {
                        $fail($conflict);
                    }
                },
            ],
            'asset_code' => ['nullable', 'string', 'max:255'],                        // 資產編號（學校財產編號），可不填
            'device_category_id' => ['required', 'exists:device_categories,id'],      // 設備類別：必須是真的存在的類別
            'brand' => ['nullable', 'string', 'max:255'],                             // 品牌
            'model' => ['nullable', 'string', 'max:255'],                             // 型號
            'serial_number' => ['nullable', 'string', 'max:255'],                     // 序號
            'warranty_end_date' => ['nullable', 'date'],                                 // 保固到期日
            // 教室不能指到已被軟刪除的教室，exists 預設不看 deleted_at
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')->whereNull('deleted_at')],
            // 狀態只能是 Device::STATUSES 列出的幾種（normal / retired / disabled；待維修由報修單推算，不能手動設）。
            'status' => ['required', 'in:' . implode(',', Device::STATUSES)],
        ], [
            'device_code.max' => EquipmentCode::formatMessage('設備編號'),
            'device_code.regex' => EquipmentCode::formatMessage('設備編號'),
            // 已停用（軟刪除）的設備編號仍被占用，要讓人知道原因。
            'device_code.unique' => '這個設備編號已經被使用（可能是已停用的設備，停用的設備編號不能重複使用）。',
        ]);
    }
}
