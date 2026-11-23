<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsList;
use App\Support\RepairExportColumns;
use App\Enums\RepairRequestStatus;                      // 報修單狀態列舉（新報修／已派工／已退回／待驗收／已結案）
use App\Models\Classroom;                               // 教室資料表的模型（看板篩選列的教室下拉選單）
use App\Models\Device;                                  // 設備資料表的模型（重複報修判斷門檻 Device::REPEAT_REPAIR_DAYS/THRESHOLD）
use App\Models\KnowledgeBase;                           // 知識庫文章的模型
use App\Models\RepairRequest;                           // 報修單資料表的模型
use App\Models\User;                                    // 用戶資料表的模型
use App\Http\Requests\AssignRepairRequestRequest;       // 「派工」表單的驗證規則
use App\Http\Requests\RejectRepairRequestRequest;       // 「驗收退回」表單的驗證規則
use App\Http\Requests\ReturnRepairRequestRequest;       // 「退回報修單」表單的驗證規則（必填原因）
use App\Http\Requests\StoreRepairRequestRequest;        // 「新增報修」表單的驗證規則
use App\Http\Requests\UpdateRepairRequestRequest;       // 「修改報修單」表單的驗證規則
use App\Services\AttachmentUploader;                    // 負責存放上傳附件（照片）的服務
use App\Services\AuditLogger;                           // 共用的操作紀錄寫入工具
use App\Services\RepairAssignmentNotifier;              // 派工後寄通知信的服務
use App\Services\RepairRequestWorkflow;                 // 報修單狀態轉換規則（誰能從哪個狀態變到哪個狀態）
use DomainException;                                    // 「違反業務規則」的例外，狀態轉換不合法時由 Workflow 丟出
use Illuminate\Http\Request;                            // 這一次瀏覽器送來的請求
use Illuminate\Support\Carbon;                          // 日期處理（新增日期範圍篩選）
use Illuminate\Support\Facades\Auth;                    // 取得目前登入的人
use Illuminate\Support\Facades\Gate;                    // 權限判斷：Gate::authorize 不通過就直接回 403

/**
 * 報修單的網頁功能都在這支 Controller。每個 public 方法對應一個網址（見
 * routes/web.php），負責「接收使用者的請求 → 呼叫 Model/Service 做事 →
 * 回傳網頁或跳轉」，實際的規則（例如狀態能不能轉換）都不寫在這裡，而是丟給
 * App\Services\RepairRequestWorkflow 處理，這支 Controller 只負責「串接」。
 *
 * 報修單的生命週期（狀態流程）：
 *   新報修(pending) → 已派工(assigned) → 待驗收(pending_review) → 已結案(completed)
 *   驗收不通過時：待驗收 → 退回已派工；新報修、已派工可以退回（已退回 returned，流程結束）。
 * 誰能做哪一步由權限決定（見 routes/web.php 的 can: 設定）：派工 repairs.dispatch、
 * 處理 repairs.process、驗收 repairs.accept。
 *
 * 【想新增一個報修單欄位】migration 加欄位 → Models/RepairRequest.php 的 $fillable →
 *   Http/Requests/StoreRepairRequestRequest.php 加驗證 → resources/views/repairs/create.blade.php 加輸入框、
 *   show.blade.php 加顯示。
 */
class RepairRequestController extends Controller
{
    use ExportsList;

    /**
     * 維修案件看板（依《第 2 週個人工作計畫》第 2 項，第三週補強看板資訊）。
     *
     * 可以用網址參數 ?status=xxx 依狀態篩選、?location=xxx 依教室/地點篩選
     * （模糊比對，例如打 "A1" 也找得到 "A101"）、?assignee=xxx 依維修人員篩選
     * （依《第四週個人工作計畫》第 1 項新增，一樣是模糊比對）。
     * 主控台的圖表、通知鈴鐺的連結也是帶這些參數過來。
     *
     * 另外會算出「每個維修人員手上還有幾張未結案的案件」，讓主管可以看客觀
     * 資料自己判斷要不要多分派給某人（規格明確要求「不做自動派工推薦」，
     * 所以這裡只顯示數字，不會自動幫忙排序或指定人選）。
     */
    public function index(Request $request)
    {
        $repairRequests = $this->indexQuery($request)
            ->paginate(15)        // 每頁 15 張（全站列表一致）
            ->withQueryString();  // 換頁時保留篩選條件

        // 篩選列的狀態下拉選單：列出列舉裡所有的狀態。
        $statuses = RepairRequestStatus::cases();

        // 統計「每個維修人員手上還有幾張還沒結案的案件」，現在以真正的 assigned_to
        // （users.id）分組（不會被同名不同人混淆）。
        $activeCaseCountsByAssignee = RepairRequest::query()
            ->visibleTo($request->user())                                        // 同樣只算這位用戶看得到的案件
            ->whereNotNull('assigned_to')                                        // 只算有指派人的
            ->open()                                                             // 已結案、已退回的不算「手上還在做」
            ->selectRaw('assigned_to, count(*) as active_count')                 // 每個人各幾張
            ->groupBy('assigned_to')
            ->pluck('active_count', 'assigned_to');                              // 結果：[用戶編號 => 張數]

        // 篩選列「教室」下拉選單的選項。
        $classrooms = Classroom::orderBy('classroom_code')->get();

        // 「匯出 Excel」預覽視窗要列的報修單（目前搜尋與篩選條件下最多 500 張）；在對話框裡看「我的報修」時不用。
        $exportRepairs = $request->boolean('modal') ? collect() : $this->exportQuery($request)->limit(500)->get();

        // 從設備主檔連過來（?device_id=）：篩選列沒有這個欄位，另外用一行灰字說明，附清除連結。
        $filteredDevice = $request->filled('device_id') ? Device::withTrashed()->find($request->integer('device_id')) : null;
        $activeFilters = $this->describeActiveFilters($request);

        return view('repairs.index', compact('repairRequests', 'statuses', 'activeCaseCountsByAssignee', 'classrooms', 'exportRepairs', 'filteredDevice', 'activeFilters'));
    }

    /** 沒有任何派工、維修、驗收權限：看板直接顯示「我的報修」（只列自己提出的報修單）。 */
    public static function onlyOwnRepairs(?\App\Models\User $user): bool
    {
        return $user !== null && ! $user->can('repairs.dispatch') && ! $user->can('repairs.process') && ! $user->can('repairs.accept');
    }

    /**
     * 看板網址上的狀態篩選：['overdue']（逾時）、一個或好幾個狀態代碼（逗號分開，不認得的代碼略過），或 []（全部）。
     * ?open=1（教室、設備主檔的「進行中」數字連過來）＝未結案：新報修、已派工、待驗收（已結案、已退回不算）。
     * 狀態篩選都會顯示在篩選列的「狀態」欄（好幾個狀態時顯示「新報修、已派工、待驗收」），不另外放提示框。
     *
     * @return list<string>
     */
    public static function boardStatusFilter(Request $request): array
    {
        if ($request->boolean('overdue') || $request->input('status') === 'overdue') {
            return ['overdue'];
        }
        $valid = array_map(fn (RepairRequestStatus $status) => $status->value, RepairRequestStatus::cases());
        if ($request->boolean('open') && ! $request->filled('status')) {
            return array_values(array_diff($valid, RepairRequestStatus::closedValues()));
        }

        return array_values(array_intersect(array_map('trim', explode(',', (string) $request->input('status', ''))), $valid));
    }

    /**
     * 目前套用的「進階篩選」說明（網址帶過來、篩選列沒有對應欄位的那幾個），畫面上每一項顯示成一行灰字，並附清除連結，
     * 讓人一看就知道「現在只看什麼」。回傳 [['text' => 說明, 'clear' => 要移除的參數名稱們], ...]。
     *
     * @return array<int, array{text: string, clear: array<int, string>}>
     */
    private function describeActiveFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('created_from') || $request->filled('created_to')) {
            $from = $request->string('created_from')->toString();
            $to = $request->string('created_to')->toString();
            $range = $from !== '' && $from === $to ? $from : (($from ?: '最早') . ' 至 ' . ($to ?: '今天'));
            $filters[] = ['text' => '只顯示 ' . $range . ' 新增的報修單', 'clear' => ['created_from', 'created_to']];
        }
        if ($request->filled('ended_from') || $request->filled('ended_to')) {
            $from = $request->string('ended_from')->toString();
            $to = $request->string('ended_to')->toString();
            $filters[] = ['text' => '只顯示維修在 ' . ($from ?: '最早') . ' 至 ' . ($to ?: '今天') . ' 期間完成的報修單', 'clear' => ['ended_from', 'ended_to']];
        }
        if ($request->filled('device_classroom_id')) {
            $room = Classroom::find($request->integer('device_classroom_id'));
            $filters[] = ['text' => '只顯示放在「' . ($room ? $room->classroom_code . ' ' . $room->name : '這間教室') . '」的設備的報修單', 'clear' => ['device_classroom_id']];
        }
        if ($request->filled('device_category_id')) {
            $category = \App\Models\DeviceCategory::find($request->integer('device_category_id'));
            $filters[] = ['text' => '只顯示設備類別為「' . ($category ? $category->name : '這個類別') . '」的報修單', 'clear' => ['device_category_id']];
        }
        if ($request->boolean('assigned_to_me')) {
            $filters[] = ['text' => '只顯示指派給我的報修單', 'clear' => ['assigned_to_me']];
        }
        if ($request->boolean('acceptable')) {
            $filters[] = ['text' => '只顯示等你驗收的報修單', 'clear' => ['acceptable']];
        }

        return $filters;
    }

    /**
     * 看板的查詢（列表與匯出共用）：只列這位用戶看得到的案件，再套用網址上的搜尋與篩選條件，最新的在前面。
     */
    private function indexQuery(Request $request)
    {
        $closed = RepairRequestStatus::closedValues();
        $closedMarks = implode(', ', array_fill(0, count($closed), '?'));

        // 網址上的篩選條件格式不對（例如被亂改成 status[]=x）時，回到上一頁並提示，不要整頁 500。
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:120'],
            'assignee' => ['nullable', 'string', 'max:100'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'ended_from' => ['nullable', 'date_format:Y-m-d'],
            'ended_to' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['keyword' => '關鍵字', 'status' => '狀態', 'assignee' => '維修人員', 'created_from' => '新增日期（起）', 'created_to' => '新增日期（迄）', 'ended_from' => '完修日期（起）', 'ended_to' => '完修日期（迄）']);

        return RepairRequest::query()
            // 只列出這位用戶看得到的案件：能派工的人看全部，其他人只看自己報修的或指派給自己的（規則見 RepairRequestPolicy）。
            ->visibleOnBoard($request->user())
            // with：順便查好設備與被指派的維修人員，看板上顯示名稱時不用每張單再多查一次。
            // 設備另外帶上「近 30 天這台設備被報修幾次」，看板才能直接標示重複報修的設備（不用再連去設備主檔才看得到）。
            ->with([
                'device' => fn ($query) => $query->with('classroom')
                    ->withCount(['repairRequests as recent_repairs_count' => fn ($repairs) => $repairs
                        ->where('created_at', '>=', now()->subDays(Device::REPEAT_REPAIR_DAYS))
                        ->where('status', '<>', \App\Enums\RepairRequestStatus::Returned->value)]),   // 已退回的不算
                'classroom',
                'assignedTechnician',
            ])
            // 教室篩選（篩選列的「教室」，教室主檔的連結也帶這個參數過來）：
            //   ?classroom_id= 只看這間教室的報修單：以發現地點為準，沒填發現地點的才看設備現在放在哪（見 RepairRequest::scopeLocatedIn）。
            // 設備主檔連過來時改用關鍵字（?keyword=設備編號），直接顯示在篩選列的關鍵字欄位裡。
            // ?device_id= 只看這台設備的報修單（設備主檔的「重複報修」「進行中」連結帶過來）。
            ->when($request->filled('device_id'), fn ($query) => $query->where('device_id', $request->integer('device_id')))
            ->when($request->filled('classroom_id'), fn ($query) => $query->locatedIn($request->integer('classroom_id')))
            // 關鍵字搜尋：標題、故障描述、設備位置文字、設備（編號、資產編號、品牌、型號、序號）、教室（名稱、代碼）、報修人姓名；
            // 輸入 12 或 #12 也找得到編號 12 的報修單。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = trim($request->string('keyword')->toString());
                $like = '%' . $keyword . '%';
                // 關鍵字剛好是某台設備的完整編號（例如從設備主檔點過來）：設備編號只比對完全相同的，
                // 不然 DEV-0001 也會找到 DEV-00011，數量就跟設備主檔顯示的不一樣。
                $exactDevice = Device::withTrashed()->where('device_code', $keyword)->exists();
                $query->where(function ($q) use ($like, $keyword, $exactDevice) {
                    $q->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('device_note', 'like', $like)
                        ->orWhereHas('device', fn ($device) => $device->where('device_code', $exactDevice ? '=' : 'like', $exactDevice ? $keyword : $like)->orWhere('asset_code', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial_number', 'like', $like))
                        ->orWhereHas('classroom', fn ($room) => $room->where('name', 'like', $like)->orWhere('classroom_code', 'like', $like))
                        ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', $like))
                        // 維修人員也用關鍵字搜（原本單獨一格「維修人員」，併進關鍵字）。
                        ->orWhereHas('assignedTechnician', fn ($user) => $user->where('name', 'like', $like));
                    if (ctype_digit(ltrim($keyword, '#'))) {
                        $q->orWhere('id', (int) ltrim($keyword, '#'));
                    }
                });
            })
            // 我的報修：?mine=1 只看「我提出的」報修單（選單的「我的報修」連過來）；所有身分都能用，管理員與主管也能只看自己提的。
            // 沒有派工、維修、驗收權限的人（例如學生、一般教師）看板就是「我的報修」，固定只列自己提出的。
            ->when($request->boolean('mine') || self::onlyOwnRepairs($request->user()), fn ($query) => $query->where('requester_id', $request->user()->id))
            // 狀態篩選（見 boardStatusFilter）：
            //   ?status=overdue：只看已逾時（未結案且超過預計處理時間）的案件（主控台「逾時工單」卡片；舊網址 ?overdue=1 也一樣）。
            //   ?status=assigned,pending_review：好幾個狀態用逗號分開（主控台「進行中工單」＝未結案、「待修數」＝新報修＋已派工）。
            //   ?open=1：未結案（教室、設備主檔的「進行中」數字連過來）。
            ->when(self::boardStatusFilter($request) === ['overdue'], fn ($query) => $query->overdue())
            ->when(self::boardStatusFilter($request) !== [] && self::boardStatusFilter($request) !== ['overdue'], fn ($query) => $query->whereIn('status', self::boardStatusFilter($request)))
            // 新增日期範圍：?created_from=2026-11-01&created_to=2026-11-30（含頭尾），主控台「本月新增」與每日趨勢圖連過來。
            ->when($request->filled('created_from'), fn ($query) => $query->where('created_at', '>=', Carbon::parse($request->string('created_from')->toString())->startOfDay()))
            ->when($request->filled('created_to'), fn ($query) => $query->where('created_at', '<=', Carbon::parse($request->string('created_to')->toString())->endOfDay()))
            // 完修日期範圍：有維修紀錄在這段期間結束（?ended_from=&ended_to=），主控台「本月案件平均工時」卡片連過來，
            // 列出來的就是納入平均的那些已結案報修單。
            ->when($request->filled('ended_from') || $request->filled('ended_to'), fn ($query) => $query->whereHas('repairLogs', function ($logs) use ($request) {
                $logs->whereNotNull('ended_at')
                    ->when($request->filled('ended_from'), fn ($q) => $q->where('ended_at', '>=', Carbon::parse($request->string('ended_from')->toString())->startOfDay()))
                    ->when($request->filled('ended_to'), fn ($q) => $q->where('ended_at', '<=', Carbon::parse($request->string('ended_to')->toString())->endOfDay()));
            }))
            // 只看「設備放在這間教室」的報修單（?device_classroom_id=，教室主檔的進行中數字與排行圖用，算法和數字一致；
            // 看板上的「教室」下拉選單另外也會把「設備地點寫這間教室」的報修單算進來）。
            ->when($request->filled('device_classroom_id'), fn ($query) => $query->whereHas('device', fn ($device) => $device->withoutTrashed()->where('classroom_id', $request->integer('device_classroom_id'))))
            // 只看某個設備類別（?device_category_id=，設備類別排行圖連過來）。
            ->when($request->filled('device_category_id'), fn ($query) => $query->whereHas('device', fn ($device) => $device->withoutTrashed()->where('device_category_id', $request->integer('device_category_id'))))
            // 只看「等我驗收」的待驗收案件（?acceptable=1，通知鈴鐺「待驗收」連過來，和鈴鐺數字同一個定義）。
            ->when($request->boolean('acceptable'), fn ($query) => $query->acceptableBy($request->user()))
            // 只看指派給我的（?assigned_to_me=1，通知鈴鐺「指派給我」連過來；用帳號編號比對，不會被同名的人混淆）。
            ->when($request->boolean('assigned_to_me'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            // 維修人員篩選：對「已指派的維修人員姓名」模糊比對（維修人員是 assigned_to 指向的真實帳號）。
            ->when($request->filled('assignee'), fn ($query) => $query->whereHas(
                'assignedTechnician',
                fn ($uq) => $uq->where('name', 'like', '%' . $request->string('assignee') . '%')
            ))
            // 排序：
            // 1. 逾時（還沒結案、已經超過預計處理時間）的排最上面；逾時的之間先排「影響上課」的，
            //    是否影響上課相同時，逾時越久（預計處理時間越早）越上面；
            // 2. 其他的先排還在處理的（新報修、已派工、待驗收），再排已結束的（已結案、已退回）；
            // 3. 同一組裡依送出時間，近的在上；最後用編號，讓完全相同的幾筆每次順序都一樣。
            // 已結束的狀態有幾個（已結案、已退回、已中止），NOT IN 的問號就放幾個。
            ->orderByRaw(
                "CASE WHEN repair_requests.status NOT IN ({$closedMarks}) AND repair_requests.scheduled_at IS NOT NULL AND repair_requests.scheduled_at < ? THEN 0 "
                . "WHEN repair_requests.status NOT IN ({$closedMarks}) THEN 1 ELSE 2 END",
                [...$closed, now()->toDateTimeString(), ...$closed]
            )
            ->orderByRaw(
                "CASE WHEN repair_requests.status NOT IN ({$closedMarks}) AND repair_requests.scheduled_at IS NOT NULL AND repair_requests.scheduled_at < ? THEN repair_requests.is_class_affected END DESC",
                [...$closed, now()->toDateTimeString()]
            )
            ->orderByRaw(
                "CASE WHEN repair_requests.status NOT IN ({$closedMarks}) AND repair_requests.scheduled_at IS NOT NULL AND repair_requests.scheduled_at < ? THEN repair_requests.scheduled_at END ASC",
                [...$closed, now()->toDateTimeString()]
            )
            ->orderByDesc('repair_requests.created_at')
            ->orderByDesc('repair_requests.id');
    }

    /**
     * 匯出 Excel（GET /repairs/export）：匯出「目前搜尋與篩選條件」下的報修單（不分頁），
     * 和看板一樣只含這位用戶看得到的案件；預覽視窗會帶 columns[] 與 ids[]，流程見 ExportsList（會寫一筆操作紀錄）。
     */
    public function export(Request $request)
    {
        return $this->exportList($request, $this->exportQuery($request), RepairExportColumns::class, '報修單', '報修單-', '報修單');
    }

    /** 匯出與預覽共用的查詢：看板的查詢，再一併載入匯出欄位要用的關聯。 */
    private function exportQuery(Request $request)
    {
        return $this->indexQuery($request)->with(['requester', 'device.category']);
    }

    /**
     * 顯示「新增報修」表單。
     *
     * 支援兩種簡化流程帶入資料：
     * - from_kb：從 Knowledge Base 頁面「無法排除，前往報修」連結過來，帶入描述提示。
     * - device：掃描設備條碼／QR（林政寬的 devices.entry 設備入口頁「前往報修」按鈕，
     *   或直接在表單頁用條碼輸入框查詢）過來，自動帶入設備名稱、型號、所在教室等
     *   資訊，使用者不用再自己打字描述設備，達到「簡化報修流程」的目的。
     */
    public function create()
    {
        $fromKnowledgeBase = null;
        $device = null;

        // 網址有帶 ?from_kb=文章編號 → 找出那篇知識庫文章，畫面上會顯示「你剛剛看過這篇」的提示。
        if (request()->filled('from_kb')) {
            $fromKnowledgeBase = KnowledgeBase::visibleTo(auth()->user())->find(request()->integer('from_kb'));
        }

        // 網址有帶 ?device=設備編號 → 找出那台設備（連同類別、教室），表單會自動帶入設備資訊。
        if (request()->filled('device')) {
            $device = Device::with(['category', 'classroom'])->find(request()->integer('device'));
            // 已淘汰的設備不能報修：不帶入設備，並在頁面上說明原因。
            if ($device?->status === 'retired') {
                session()->now('error', "設備 {$device->device_code} 已淘汰，無法報修。");
                $device = null;
            }
        }

        // 發現地點只能從現有、啟用中的教室選（和空間預約的選法相同）。
        $classrooms = Classroom::query()->where('is_active', true)->orderBy('classroom_code')->get();

        return view('repairs.create', compact('fromKnowledgeBase', 'device', 'classrooms'));
    }

    /**
     * 設備條碼／QR 掃描查詢（給新增報修頁的「掃描設備條碼」欄位用 AJAX 查詢），
     * 回傳 JSON，前端 JS 收到後即時把設備資訊填進表單欄位，不用整頁重新導向，
     * 是「簡化報修流程」的核心功能。
     *
     * 掃描到的內容有好幾種可能，這裡都要接得住：
     * - 設備編號（device_code，一般條碼貼紙或手動輸入）
     * - 資產編號（asset_code）、序號（serial_number）
     * - 設備 QR 貼紙：編碼的是整串網址 https://.../d/{device_code}（見 DeviceController::qrcode），
     *   掃到整串網址時只取最後的設備編號。
     */
    public function deviceLookup(Request $request)
    {
        // 取出 ?code= 的內容並去掉前後空白。
        $code = trim((string) $request->query('code'));

        // 如果掃到的是整串網址（含 /d/設備編號），用正規表示式把設備編號那段抓出來。
        // urldecode：把網址編碼還原（例如 %2D 還原成 -）。
        if (preg_match('#/d/([^/?\#\s]+)#', $code, $matches)) {
            $code = urldecode($matches[1]);
        }

        // 什麼都沒掃到 → 回 404（找不到）。
        abort_if($code === '', 404);

        // 用「設備編號、資產編號、序號」任何一個去比對，找到的第一台設備就是結果。
        $device = Device::with(['category', 'classroom'])
            ->where(fn ($query) => $query
                ->where('device_code', $code)
                ->orWhere('asset_code', $code)
                ->orWhere('serial_number', $code))
            ->first();

        // 沒有任何設備符合 → 回 404，前端會顯示「找不到設備」。
        abort_if($device === null, 404);

        // 已淘汰的設備不能報修：回 422 和原因，前端會顯示這句話，不會把設備帶進表單。
        if ($device->status === 'retired') {
            return response()->json(['message' => "設備 {$device->device_code} 已淘汰，無法報修。"], 422);
        }

        // 回傳前端要的資料：設備編號、顯示用的一行描述，以及建議的報修標題。
        return response()->json([
            'id' => $device->id,
            'device_code' => $device->device_code,
            'serial_number' => $device->serial_number,   // 掃到序號時，畫面上一併顯示，讓使用者確認是同一台
            'display' => trim(
                ($device->category->name ?? '') . ' ' . $device->brand . ' ' . $device->model
                . '（' . ($device->classroom->name ?? $device->classroom->classroom_code ?? '') . '）'
            ),
            'title_suggestion' => trim(($device->classroom->classroom_code ?? '') . ' ' . ($device->category->name ?? '') . '故障'),
            // 設備所在教室：表單的「發現地點」會自動帶入這間教室。
            'classroom' => $device->classroom ? [
                'id' => $device->classroom->id,
                'label' => $device->classroom->name . '（' . $device->classroom->classroom_code . '）',
            ] : null,
        ]);
    }

    /**
     * 使用者送出「新增報修」表單後，真正把資料存進資料庫。
     * 附件（例如故障照片）如果有上傳，會另外存進 attachments 表；requester_id
     * 現在直接用登入者的 id（auth 中介層保證一定有登入者，不會是 null）。
     */
    public function store(StoreRepairRequestRequest $request, AttachmentUploader $attachmentUploader)
    {
        // 建立報修單：先放入驗證通過的欄位（但不含 attachments 檔案），再補上報修人 = 目前登入者。
        // ... 是「把陣列展開放進來」。
        $data = $request->safe()->except('attachments');

        // 有掃描設備、但沒選地點時，地點就用設備目前所在的教室。
        if (empty($data['classroom_id']) && ! empty($data['device_id'])) {
            $data['classroom_id'] = Device::query()->whereKey($data['device_id'])->value('classroom_id');
        }

        $repairRequest = RepairRequest::create([
            ...$data,
            'requester_id' => Auth::id(),
        ]);

        // 有上傳附件才處理；storeMany 會把每個檔案存起來並在 attachments 資料表留一筆。
        if ($request->hasFile('attachments')) {
            $attachmentUploader->storeMany($repairRequest, $request->file('attachments'));
        }

        // 寫操作紀錄：誰新增了哪張報修單。
        AuditLogger::log('created', $repairRequest, [
            'title' => $repairRequest->title,
            'device_id' => $repairRequest->device_id,
            'impact_level' => $repairRequest->impact_level,
        ], __('audit.messages.repair_created', ['title' => $repairRequest->title]));

        // 送出後直接進到這張報修單的詳細頁，並顯示「已送出」訊息。
        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.submitted'));
    }

    /**
     * 報修單詳細頁：顯示這張報修單的所有資訊、附件，以及底下的維修紀錄。
     */
    public function show(RepairRequest $repairRequest)
    {
        // 只有看得到這張單的人才能開（不是自己報修、也不是指派給自己、又沒有派工權限 → 403）。
        Gate::authorize('view', $repairRequest);

        // 補查畫面要用到的關聯資料（附件、維修紀錄與其附件、設備與其類別／教室、維修人員）。
        $repairRequest->load([
            'attachments',
            'returnedBy',
            'repairLogs.attachments',
            'repairLogs.partUsages.part',
            'device.category',
            'device.classroom',
            'classroom',
            'assignedTechnician',
            'acceptor',
        ]);

        // 派工／重新指派表單要挑選真正的維修人員，不再讓主管自己打字輸入姓名。
        // 誰能被指派由身分主檔決定：身分有勾選「可被指派為維修人員」權限、且帳號啟用中的用戶。
        // 驗收人下拉選單：身分有驗收權限（或是系統管理員）、帳號啟用中的用戶。
        $acceptors = User::where('is_active', true)
            ->whereHas('role', fn ($q) => $q->withPermission('repairs.accept')->orWhere('role_code', 'admin'))
            ->orderBy('name')
            ->get();

        $technicians = User::whereHas('role', fn ($q) => $q->withPermission('repairs.assignable'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // 計算這張報修單目前已記錄的材料費。
        $partUsages = $repairRequest->repairLogs
            ->flatMap(fn ($log) => $log->partUsages);

        $hasIncompletePrice = $partUsages->contains(
            fn ($usage) => $usage->unit_price === null
        );

        $materialCost = $hasIncompletePrice
            ? null
            : $partUsages->sum(
                fn ($usage) => $usage->quantity * $usage->unit_price
            );

        return view('repairs.show', compact(
            'repairRequest',
            'technicians',
            'acceptors',
            'partUsages',
            'materialCost',
            'hasIncompletePrice'
        ));
    }

    /**
     * 人工派工（依《第 2 週個人工作計畫》第 3 項）：新報修 -> 已派工。
     * 實際的狀態檢查與寫入邏輯都在 RepairRequestWorkflow::assign()，
     * 這裡只負責接收表單資料、呼叫它、處理成功/失敗後要跳轉去哪裡。
     */
    public function assign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow, RepairAssignmentNotifier $notifier)
    {
        Gate::authorize('view', $repairRequest);   // 跟自己無關的「待驗收／已結案」案件，連派工動作都不行
        // 表單送來的是維修人員的編號，這裡找出完整的用戶資料（找不到會自動回 404）。
        $technician = User::findOrFail($request->validated('assigned_to'));
        $from = $repairRequest->status;   // 記住派工前的狀態，寫操作紀錄時要記「從哪個狀態到哪個狀態」

        try {
            // 呼叫 Workflow 做派工；如果目前狀態不能派工（例如已經被別人派了），它會丟出 DomainException。
            $workflow->assign(
                $repairRequest,
                $technician->id,                       // 維修人員的用戶編號
                $request->validated('scheduled_at'),   // 預計處理時間
            );
        } catch (DomainException $exception) {
            // 不合法就導回詳細頁並顯示錯誤，不往下執行（也就不會寄信、不會寫成功紀錄）。
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        // 指定驗收人（可不填，不填＝報修人本人驗收）。
        $repairRequest->update(['accepted_by' => $request->validated('accepted_by')]);

        // 派工成功才寫操作紀錄。?-> 是「如果預計時間沒填（null）就不要報錯」。
        AuditLogger::log('assigned', $repairRequest, [
            'technician' => $technician->name,
            'acceptor' => $repairRequest->acceptor?->name,
            'scheduled_at' => $repairRequest->scheduled_at?->format('Y-m-d H:i'),
            'from' => $from->value,
            'to' => $repairRequest->status->value,
        ], __('audit.messages.repair_assigned', ['technician' => $technician->name, 'title' => $repairRequest->title]));

        // 寄通知信給被派工的維修人員（以及身分有「收到派工通知副本」權限的人）。
        $notifier->notify($repairRequest, $technician);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.dispatched'));
    }

    /**
     * 重新指派（依《第四週個人工作計畫》第 1 項）：案件已經派過工了，但主管想
     * 換一個維修人員或改一下處理日期，不需要重新走一次狀態轉換。
     * 跟 assign() 共用同一份表單驗證規則（欄位一模一樣），只是呼叫的 workflow
     * 方法不同：assign() 會把狀態從「新報修」轉成「已派工」，reassign() 只換人。
     */
    public function reassign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow, RepairAssignmentNotifier $notifier)
    {
        Gate::authorize('view', $repairRequest);
        $technician = User::findOrFail($request->validated('assigned_to'));

        try {
            // 只換人與時間，狀態不變；不合法（例如案件已結案）會丟 DomainException。
            $workflow->reassign(
                $repairRequest,
                $technician->id,
                $request->validated('scheduled_at'),
            );
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        // 重新指派時一併更新驗收人（表單送來才改；沒有這個欄位就維持原本的）。
        if ($request->has('accepted_by')) {
            $repairRequest->update(['accepted_by' => $request->validated('accepted_by')]);
        }

        AuditLogger::log('reassigned', $repairRequest, [
            'technician' => $technician->name,
            'acceptor' => $repairRequest->acceptor?->name,
            'scheduled_at' => $repairRequest->scheduled_at?->format('Y-m-d H:i'),
        ], __('audit.messages.repair_reassigned', ['technician' => $technician->name, 'title' => $repairRequest->title]));

        // 新的維修人員也要收到通知信。
        $notifier->notify($repairRequest, $technician);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.reassigned'));
    }

    /**
     * 報修人驗收通過，結案：待驗收 -> 已結案（依《第三週個人工作計畫》第 2 項）。
     * 已結案之後，這張報修單就不能再變動狀態了。
     */
    public function complete(RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        // 只有報修人本人（或系統管理員、或沒有報修人的案件由有驗收權限的人）能驗收，其他人 → 403。
        Gate::authorize('accept', $repairRequest);

        $from = $repairRequest->status;

        try {
            $workflow->complete($repairRequest);
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        $this->logStatusChange($repairRequest, $from);

        // 驗收通過：到詳細頁用彈出視窗顯示「結案資訊」（案件、設備、維修人員、維修紀錄與工時、用料、驗收人、結案時間）。
        $repairRequest->load(['device.classroom', 'classroom', 'assignedTechnician', 'repairLogs.partUsages.part']);
        $hours = 0.0;
        $partTotals = [];
        foreach ($repairRequest->repairLogs as $log) {
            $hours += (float) $log->total_hours;
            foreach ($log->partUsages as $usage) {
                $name = $usage->part ? $usage->part->name : '（已刪除的零件）';
                $partTotals[$name] = ($partTotals[$name] ?? 0) + $usage->quantity;
            }
        }
        $partLines = [];
        foreach ($partTotals as $name => $quantity) {
            $partLines[] = $name . ' × ' . $quantity;
        }
        $device = $repairRequest->device;
        $where = $device
            ? trim($device->device_code . ' ' . ($device->classroom ? $device->classroom->name : ''))
            : ($repairRequest->locationLabel() ?: ($repairRequest->device_note ?: '未填寫'));
        $technician = $repairRequest->assignedTechnician;
        $acceptor = Auth::user();

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.completed'))
            ->with('closing_info', [
                ['案件', '#' . $repairRequest->id . ' ' . $repairRequest->title],
                ['設備／地點', $where],
                ['維修人員', $technician ? $technician->name : '—'],
                ['維修紀錄', $repairRequest->repairLogs->count() . ' 筆，共 ' . number_format($hours, 2) . ' 小時'],
                ['使用零件', $partLines ? implode('、', $partLines) : '沒有使用零件'],
                ['驗收人', $acceptor ? $acceptor->name : '—'],
                ['結案時間', now()->format('Y-m-d H:i')],
            ]);
    }

    /**
     * 報修人驗收不通過，退回重新處理：待驗收 -> 已派工，並記錄退回原因
     * （依《第三週個人工作計畫》第 3 項「驗收退回」）。
     * 之前的維修紀錄不會被刪除，維修人員之後會再填一筆新的維修紀錄。
     */
    public function reject(RejectRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        // 和「驗收通過」同一條規則：只有指定的驗收人（或報修人本人、系統管理員）能退回，其他有驗收權限的人 → 403。
        Gate::authorize('accept', $repairRequest);

        try {
            // 退回原因是必填的（由 RejectRepairRequestRequest 驗證），會一起存進報修單。
            $workflow->reject($repairRequest, $request->validated('rejection_reason'));
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        AuditLogger::log('rejected', $repairRequest, [
            'reason' => $request->validated('rejection_reason'),
        ], __('audit.messages.repair_rejected', ['title' => $repairRequest->title]));

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.rejected'));
    }

    /** 把一次狀態變更寫進操作紀錄：「報修單「xxx」狀態：待驗收 → 已結案」。 */
    private function logStatusChange(RepairRequest $repairRequest, RepairRequestStatus $from): void
    {
        AuditLogger::log('status_changed', $repairRequest, [
            'from' => $from->value,                          // 變更前的狀態代碼
            'to' => $repairRequest->status->value,           // 變更後的狀態代碼
        ], __('audit.messages.repair_status', [
            'title' => $repairRequest->title,
            'from' => $from->label(),                        // label() 是狀態的中文名稱，顯示給人看
            'to' => $repairRequest->status->label(),
        ]));
    }

    /**
     * 狀態轉換不合法時（例如使用者連點兩下、開多個分頁各按一次按鈕）會用到這個。
     * 導回詳細頁顯示友善的錯誤訊息，不讓使用者看到 Laravel 預設的 500 錯誤頁。
     */
    private function redirectWithWorkflowError(RepairRequest $repairRequest, DomainException $exception)
    {
        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('error', $exception->getMessage());   // 錯誤訊息文字來自 Workflow 丟出例外時寫的說明
    }

    /**
     * 「修改報修單」表單（GET /repairs/{id}/edit）：有派工權限的人可以改全部欄位；報修人自己在派工前只能改故障描述（見 editScope）。
     * 已結案的報修單不能再改。可以改標題、描述、影響程度、是否影響上課、設備位置／描述、發現地點。
     */
    /**
     * 能不能修改這張報修單：有派工權限的人（全部欄位）；或報修人自己、還沒派工（新報修）時（只能改故障描述）。
     * 回傳 'all'、'description' 或 null（不能改）。
     */
    public static function editScope(?\App\Models\User $user, RepairRequest $repairRequest): ?string
    {
        if ($user === null || $repairRequest->status->isClosed()) {
            return null;
        }
        if ($user->can('repairs.dispatch')) {
            return 'all';
        }

        return $repairRequest->requester_id === $user->id && $repairRequest->status === RepairRequestStatus::Pending ? 'description' : null;
    }

    public function edit(RepairRequest $repairRequest)
    {
        Gate::authorize('view', $repairRequest);   // 跟自己無關的案件看不到，也不能開修改表單
        if (! $repairRequest->status->isClosed() && self::editScope(auth()->user(), $repairRequest) === null) {
            abort(403);   // 沒有派工權限、也不是還沒派工的自己的報修單
        }
        if ($repairRequest->status->isClosed()) {
            return redirect()->route('repairs.show', $repairRequest)->with('error', $repairRequest->status === RepairRequestStatus::Aborted ? $this->finalEditMessage($repairRequest) : '已結案或已退回的報修單不能修改。');
        }

        $classrooms = Classroom::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $repairRequest->classroom_id))
            ->orderBy('classroom_code')
            ->get();

        return view('repairs.edit', compact('repairRequest', 'classrooms'));
    }

    /** 已結案或已中止的報修單不能修改時，顯示給使用者的說明。 */
    private function finalEditMessage(RepairRequest $repairRequest): string
    {
        return $repairRequest->status === RepairRequestStatus::Aborted
            ? '這張報修單已因設備淘汰中止，資料已定案，不能修改。'
            : '已結案的報修單不能修改。';
    }

    /** 儲存修改（PUT /repairs/{id}）：只改上面這幾個欄位，狀態、報修人、維修人員不變；寫操作紀錄。 */
    public function update(UpdateRepairRequestRequest $request, RepairRequest $repairRequest)
    {
        Gate::authorize('view', $repairRequest);   // 跟自己無關的案件看不到，也不能開修改表單
        if ($repairRequest->status->isClosed()) {
            return redirect()->route('repairs.show', $repairRequest)->with('error', $repairRequest->status === RepairRequestStatus::Aborted ? $this->finalEditMessage($repairRequest) : '已結案或已退回的報修單不能修改。');
        }

        $scope = self::editScope($request->user(), $repairRequest);
        abort_if($scope === null, 403);
        $data = $request->validated();
        // 報修人自己（沒有派工權限）在派工前只能改故障描述。
        if ($scope === 'description') {
            $data = ['description' => $data['description']];
        }
        // 已綁定的設備不能在這裡換；沒有設備的才補上（沒送就不動）。
        if ($repairRequest->device_id !== null || empty($data['device_id'])) {
            unset($data['device_id']);
        }
        $repairRequest->fill($data);
        $changes = [];
        foreach ($repairRequest->getDirty() as $field => $new) {
            $changes[$field] = ['old' => $repairRequest->getOriginal($field), 'new' => $new];
        }
        $repairRequest->save();

        if ($changes) {
            AuditLogger::log('updated', $repairRequest, $changes, '修改報修單「' . $repairRequest->title . '」');
        }

        return redirect()->route('repairs.show', $repairRequest)->with('success', '報修單已修改。');
    }

    /**
     * 退回報修單（POST /repairs/{id}/return）：取代原本的「取消（刪除）」。必填原因，狀態變成「已退回」，
     * 流程結束但仍留在看板（不算待維修）；使用者需要時重新報修。
     * 新報修由能派工的人退回、已派工由被指派的維修人員退回（ReturnRepairRequestRequest 檢查）。
     */
    public function returnRequest(ReturnRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        $from = $repairRequest->status;
        $reason = $request->validated('return_reason');

        try {
            $workflow->returnRequest($repairRequest, $reason, $request->user()->id);
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        AuditLogger::log('status_changed', $repairRequest, [
            'from' => $from->value,
            'to' => $repairRequest->status->value,
            'reason' => $reason,
        ], '退回報修單「' . $repairRequest->title . '」：' . $reason);

        return redirect()->route('repairs.show', $repairRequest)->with('success', __('repair_requests.flash.returned'));
    }
}
