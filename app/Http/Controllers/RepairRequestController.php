<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Enums\RepairRequestStatus;                      // 報修單狀態列舉（新報修／已派工／處理中／待驗收／已結案）
use App\Models\Device;                                  // 設備資料表的模型
use App\Models\KnowledgeBase;                           // 知識庫文章的模型
use App\Models\RepairRequest;                           // 報修單資料表的模型
use App\Models\User;                                    // 用戶資料表的模型
use App\Http\Requests\AssignRepairRequestRequest;       // 「派工」表單的驗證規則
use App\Http\Requests\RejectRepairRequestRequest;       // 「驗收退回」表單的驗證規則
use App\Http\Requests\StoreRepairRequestRequest;        // 「新增報修」表單的驗證規則
use App\Services\AttachmentUploader;                    // 負責存放上傳附件（照片）的服務
use App\Services\AuditLogger;                           // 共用的操作紀錄寫入工具
use App\Services\RepairAssignmentNotifier;              // 派工後寄通知信的服務
use App\Services\RepairRequestWorkflow;                 // 報修單狀態轉換規則（誰能從哪個狀態變到哪個狀態）
use DomainException;                                    // 「違反業務規則」的例外，狀態轉換不合法時由 Workflow 丟出
use Illuminate\Http\Request;                            // 這一次瀏覽器送來的請求
use Illuminate\Support\Facades\Auth;                    // 取得目前登入的人
use Illuminate\Support\Facades\Gate;                    // 權限判斷：Gate::authorize 不通過就直接回 403

/**
 * 報修單的網頁功能都在這支 Controller。每個 public 方法對應一個網址（見
 * routes/web.php），負責「接收使用者的請求 → 呼叫 Model/Service 做事 →
 * 回傳網頁或跳轉」，實際的規則（例如狀態能不能轉換）都不寫在這裡，而是丟給
 * App\Services\RepairRequestWorkflow 處理，這支 Controller 只負責「串接」。
 *
 * 報修單的生命週期（狀態流程）：
 *   新報修(pending) → 已派工(assigned) → 處理中(in_progress) → 待驗收(pending_review) → 已結案(completed)
 *   驗收不通過時：待驗收 → 退回處理中。
 * 誰能做哪一步由權限決定（見 routes/web.php 的 can: 設定）：派工 repairs.dispatch、
 * 處理 repairs.process、驗收 repairs.accept。
 *
 * 【想新增一個報修單欄位】migration 加欄位 → Models/RepairRequest.php 的 $fillable →
 *   Http/Requests/StoreRepairRequestRequest.php 加驗證 → resources/views/repairs/create.blade.php 加輸入框、
 *   show.blade.php 加顯示。
 */
class RepairRequestController extends Controller
{
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
        $repairRequests = RepairRequest::query()
            // 只列出這位用戶看得到的案件：能派工的人看全部，其他人只看自己報修的或指派給自己的（規則見 RepairRequestPolicy）。
            ->visibleTo($request->user())
            // with：順便查好設備與被指派的維修人員，看板上顯示名稱時不用每張單再多查一次。
            ->with(['device', 'assignedTechnician'])
            // 狀態篩選：有帶 ?status= 才套用。
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            // 地點篩選：like %字% 是「包含這段字」，所以打 A1 也找得到 A101。
            ->when($request->filled('location'), fn ($query) => $query->where('location', 'like', '%' . $request->string('location') . '%'))
            // 維修人員篩選：users 表合併後改成對「已指派的真實使用者姓名」模糊比對，
            // 沒有 assigned_to（例如保養 NG 轉報修等舊資料）就退回比對 assignee_note 文字。
            ->when($request->filled('assignee'), function ($query) use ($request) {
                $keyword = $request->string('assignee');
                // 括號包起來：確保「或」只在「姓名符合」與「備註文字符合」之間，不會影響上面的狀態、地點篩選。
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('assignedTechnician', fn ($uq) => $uq->where('name', 'like', "%{$keyword}%"))
                        ->orWhere('assignee_note', 'like', "%{$keyword}%");
                });
            })
            ->latest()            // 依建立時間，最新的排最前面
            ->paginate(10)        // 每頁 10 張
            ->withQueryString();  // 換頁時保留篩選條件

        // 篩選列的狀態下拉選單：列出列舉裡所有的狀態。
        $statuses = RepairRequestStatus::cases();

        // 統計「每個維修人員手上還有幾張還沒結案的案件」，現在以真正的 assigned_to
        // （users.id）分組，比用文字 assignee_note 分組更準確（不會被同名不同人混淆）。
        $activeCaseCountsByAssignee = RepairRequest::query()
            ->visibleTo($request->user())                                        // 同樣只算這位用戶看得到的案件
            ->whereNotNull('assigned_to')                                        // 只算有指派人的
            ->where('status', '!=', RepairRequestStatus::Completed->value)       // 已結案的不算「手上還在做」
            ->selectRaw('assigned_to, count(*) as active_count')                 // 每個人各幾張
            ->groupBy('assigned_to')
            ->pluck('active_count', 'assigned_to');                              // 結果：[用戶編號 => 張數]

        return view('repairs.index', compact('repairRequests', 'statuses', 'activeCaseCountsByAssignee'));
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
            $fromKnowledgeBase = KnowledgeBase::find(request()->integer('from_kb'));
        }

        // 網址有帶 ?device=設備編號 → 找出那台設備（連同類別、教室），表單會自動帶入設備資訊。
        if (request()->filled('device')) {
            $device = Device::with(['category', 'classroom'])->find(request()->integer('device'));
        }

        return view('repairs.create', compact('fromKnowledgeBase', 'device'));
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

        // 回傳前端要的資料：設備編號、顯示用的一行描述，以及建議的報修標題。
        return response()->json([
            'id' => $device->id,
            'device_code' => $device->device_code,
            'display' => trim(
                ($device->category->name ?? '') . ' ' . $device->brand . ' ' . $device->model
                . '（' . ($device->classroom->room_name ?? $device->classroom->room_code ?? '') . '）'
            ),
            'title_suggestion' => trim(($device->classroom->room_code ?? '') . ' ' . ($device->category->name ?? '') . '故障'),
        ]);
    }

    /**
     * 使用者送出「新增報修」表單後，真正把資料存進資料庫。
     * 附件（例如故障照片）如果有上傳，會另外存進 attachments 表；reporter_id
     * 現在直接用登入者的 id（auth 中介層保證一定有登入者，不會是 null）。
     */
    public function store(StoreRepairRequestRequest $request, AttachmentUploader $attachmentUploader)
    {
        // 建立報修單：先放入驗證通過的欄位（但不含 attachments 檔案），再補上報修人 = 目前登入者。
        // ... 是「把陣列展開放進來」。
        $repairRequest = RepairRequest::create([
            ...$request->safe()->except('attachments'),
            'reporter_id' => Auth::id(),
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
        $repairRequest->load(['attachments', 'repairLogs.attachments', 'device.category', 'device.classroom', 'assignedTechnician']);

        // 派工／重新指派表單要挑選真正的維修人員，不再讓主管自己打字輸入姓名。
        // 誰能被指派由身分主檔決定：身分有勾選「可被指派為維修人員」權限、且帳號啟用中的用戶。
        $technicians = User::whereHas('role', fn ($q) => $q->withPermission('repairs.assignable'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('repairs.show', compact('repairRequest', 'technicians'));
    }

    /**
     * 人工派工（依《第 2 週個人工作計畫》第 3 項）：新報修 -> 已派工。
     * 實際的狀態檢查與寫入邏輯都在 RepairRequestWorkflow::assign()，
     * 這裡只負責接收表單資料、呼叫它、處理成功/失敗後要跳轉去哪裡。
     */
    public function assign(AssignRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow, RepairAssignmentNotifier $notifier)
    {
        // 表單送來的是維修人員的編號，這裡找出完整的用戶資料（找不到會自動回 404）。
        $technician = User::findOrFail($request->validated('assigned_to'));
        $from = $repairRequest->status;   // 記住派工前的狀態，寫操作紀錄時要記「從哪個狀態到哪個狀態」

        try {
            // 呼叫 Workflow 做派工；如果目前狀態不能派工（例如已經被別人派了），它會丟出 DomainException。
            $workflow->assign(
                $repairRequest,
                $technician->name,
                $request->validated('scheduled_at'),   // 預計處理時間
                $technician->id,
            );
        } catch (DomainException $exception) {
            // 不合法就導回詳細頁並顯示錯誤，不往下執行（也就不會寄信、不會寫成功紀錄）。
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        // 派工成功才寫操作紀錄。?-> 是「如果預計時間沒填（null）就不要報錯」。
        AuditLogger::log('assigned', $repairRequest, [
            'technician' => $technician->name,
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
        $technician = User::findOrFail($request->validated('assigned_to'));

        try {
            // 只換人與時間，狀態不變；不合法（例如案件已結案）會丟 DomainException。
            $workflow->reassign(
                $repairRequest,
                $technician->name,
                $request->validated('scheduled_at'),
                $technician->id,
            );
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        AuditLogger::log('reassigned', $repairRequest, [
            'technician' => $technician->name,
            'scheduled_at' => $repairRequest->scheduled_at?->format('Y-m-d H:i'),
        ], __('audit.messages.repair_reassigned', ['technician' => $technician->name, 'title' => $repairRequest->title]));

        // 新的維修人員也要收到通知信。
        $notifier->notify($repairRequest, $technician);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.reassigned'));
    }

    /**
     * 維修人員開始處理：已派工 -> 處理中。
     */
    public function start(RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
        // 只有被指派的維修人員本人（或系統管理員）能開始處理這張單，其他人 → 403。
        Gate::authorize('start', $repairRequest);

        $from = $repairRequest->status;   // 記住改變前的狀態，給操作紀錄用

        try {
            $workflow->start($repairRequest);
        } catch (DomainException $exception) {
            return $this->redirectWithWorkflowError($repairRequest, $exception);
        }

        $this->logStatusChange($repairRequest, $from);

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.started'));
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

        return redirect()
            ->route('repairs.show', $repairRequest)
            ->with('success', __('repair_requests.flash.completed'));
    }

    /**
     * 報修人驗收不通過，退回重新處理：待驗收 -> 處理中，並記錄退回原因
     * （依《第三週個人工作計畫》第 3 項「驗收退回」）。
     * 之前的維修紀錄不會被刪除，維修人員之後會再填一筆新的維修紀錄。
     */
    public function reject(RejectRepairRequestRequest $request, RepairRequest $repairRequest, RepairRequestWorkflow $workflow)
    {
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

    /** 把一次狀態變更寫進操作紀錄：「報修單「xxx」狀態：已派工 → 處理中」。 */
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
}
