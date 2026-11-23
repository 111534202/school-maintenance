<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsList;
use App\Support\MaintenanceOrderExportColumns;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenanceOrderController extends Controller
{
    use ExportsList;

    /**
     * 保養工單列表（第 1 週任務 6；第 2 週任務 6 加上狀態/來源/日期篩選，
     * 供設備履歷、Dashboard 等其他模組之後也能穩定查詢）。
     */
    public function index(Request $request): View
    {
        $orders = $this->indexQuery($request)
            ->paginate(15)
            ->withQueryString();

        // 篩選列「負責人」下拉選單：有回報權限的人（可被指派的人）。
        $assignees = $this->assignableUsers();

        // 「匯出 Excel」預覽視窗要列的工單（目前搜尋與篩選條件下最多 500 張）。
        $exportOrders = $this->exportQuery($request)->limit(500)->get();

        return view('maintenance_orders.index', compact('orders', 'assignees', 'exportOrders'));
    }

    /** 工單列表的查詢（列表與匯出共用）：套用網址上的搜尋與篩選條件，並依狀態、排定日排好順序。 */
    private function indexQuery(Request $request)
    {
        // 網址上的篩選條件格式不對（日期不存在、被改成陣列）時回到上一頁並提示，不要整頁 500。
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:30'],
            'due' => ['nullable', 'string', 'max:30'],
            'assignee' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['keyword' => '關鍵字', 'status' => '狀態', 'source' => '來源', 'due' => '期限', 'assignee' => '負責人', 'from' => '起始日期', 'to' => '結束日期']);

        return MaintenanceOrder::query()
            ->with(['maintenancePlan', 'result', 'device', 'assignee', 'deviceCategory', 'targets.device.category', 'targets.deviceCategory'])
            // 期限：overdue＝已逾期、week＝一週內到期（含已逾期）；只看自己負責的用 mine=1；assignee＝指定負責人（none＝還沒指派）。
            ->when($request->string('due')->toString() === 'overdue', fn ($query) => $query->overdue())
            ->when($request->string('due')->toString() === 'week', fn ($query) => $query->dueWithin(7))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            // 只看還沒完成的（待處理、進行中）：?unfinished=1，通知鈴鐺「還沒指定負責人」連過來，數字和列表才會一致。
            ->when($request->boolean('unfinished'), fn ($query) => $query->unfinished())
            ->when($request->filled('assignee'), fn ($query) => $request->string('assignee')->toString() === 'none' ? $query->whereNull('assigned_to') : $query->where('assigned_to', $request->integer('assignee')))
            // 關鍵字搜尋：設備（編號、資產編號、品牌、型號）、來源計畫名稱、設備類別名稱、負責人姓名；輸入 12 或 #12 找工單編號。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = trim($request->string('keyword')->toString());
                $like = '%' . $keyword . '%';
                $query->where(function ($q) use ($like, $keyword) {
                    $q->whereHas('device', fn ($device) => $device->where('device_code', 'like', $like)->orWhere('asset_code', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial_number', 'like', $like))
                        ->orWhereHas('maintenancePlan', fn ($plan) => $plan->where('name', 'like', $like))
                        ->orWhereHas('deviceCategory', fn ($category) => $category->where('name', 'like', $like))
                        // 一張工單有好幾個保養對象時，對象清單裡的設備、類別也搜得到。
                        ->orWhereHas('targets.device', fn ($device) => $device->where('device_code', 'like', $like)->orWhere('asset_code', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('model', 'like', $like))
                        ->orWhereHas('targets.deviceCategory', fn ($category) => $category->where('name', 'like', $like))
                        ->orWhereHas('assignee', fn ($user) => $user->where('name', 'like', $like));
                    if (ctype_digit(ltrim($keyword, '#'))) {
                        $q->orWhere('maintenance_orders.id', (int) ltrim($keyword, '#'));
                    }
                });
            })
            // 狀態：待處理、進行中、已轉報修、已完成，直接照資料表的狀態篩選。
            // 狀態篩選；沒選狀態時不列「已中止」（取消的工單、設備淘汰中止的工單），要在篩選選「已中止」才看得到。
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')),
                fn ($query) => $query->where('status', '<>', MaintenanceOrder::STATUS_ABORTED))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('scheduled_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('scheduled_date', '<=', $request->date('to')))
            // 排序：
            // 1. 依狀態：待處理 → 進行中 → 已轉報修 → 已完成。
            // 2. 還沒做完的（待處理、進行中）：排定日期近的在上 → 同一天建立得早的在上。
            // 3. 已回報的（已轉報修、已完成）：排定日期遠的（最新的）在上 → 同一天建立得晚的在上。
            // 再依來源計畫名稱 A→Z，最後用工單編號，讓完全相同的幾筆每次順序都一樣。
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END',
                [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS, MaintenanceOrder::STATUS_CONVERTED])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN scheduled_date END ASC', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN status NOT IN (?, ?) THEN scheduled_date END DESC', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN created_at END ASC', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN status NOT IN (?, ?) THEN created_at END DESC', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderBy(MaintenancePlan::select('name')->whereColumn('maintenance_plans.id', 'maintenance_orders.maintenance_plan_id'))
            ->orderBy('maintenance_orders.id');
    }

    /**
     * 匯出 Excel（GET /maintenance-orders/export）：匯出「目前搜尋與篩選條件」下的保養工單（不分頁），
     * 含保養結果（OK／NG、執行人、轉報修單號）；預覽視窗會帶 columns[] 與 ids[]，流程見 ExportsList（會寫一筆操作紀錄）。
     */
    public function export(Request $request)
    {
        return $this->exportList($request, $this->exportQuery($request), MaintenanceOrderExportColumns::class, '保養工單', '保養工單-', '保養工單');
    }

    /** 匯出與預覽共用的查詢：列表的查詢，再一併載入匯出欄位要用的關聯。 */
    private function exportQuery(Request $request)
    {
        return $this->indexQuery($request)->with(['result.executor', 'result.repairRequests', 'deviceCategory', 'device.category', 'targets.device.category', 'targets.deviceCategory']);
    }

    /**
     * 保養工單詳細頁（第 1 週任務 6；第 2 週起一併顯示保養結果）。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        $maintenanceOrder->load('maintenancePlan', 'result.repairRequests', 'device', 'assignee');

        // 「指派負責人」下拉選單：有保養回報權限、帳號啟用中的人。
        $assignees = $this->assignableUsers();

        return view('maintenance_orders.show', compact('maintenanceOrder', 'assignees'));
    }

    /**
     * 保養計畫的「建立工單」按鈕：馬上產生排定在下次到期日的工單（不用等到到期日）；這一期已經有工單就不重複建立。
     * 下次到期日要等工單做完或取消才往後推。產生規則見 DueMaintenanceOrderGenerator。
     * 目前預設關閉（config/maintenance.php 的 manual_orders）：工單只由系統自動產生，按鈕不顯示、直接送出也是 404。
     */
    public function storeFromPlan(MaintenancePlan $maintenancePlan, \App\Services\DueMaintenanceOrderGenerator $generator): RedirectResponse
    {
        abort_unless(config('maintenance.manual_orders'), 404);

        if (! $maintenancePlan->is_active) {
            return redirect()->route('maintenance-plans.index')->with('error', '已停用的保養計畫不能建立工單。');
        }

        if (! $maintenancePlan->canGenerateOrders()) {
            return redirect()->route('maintenance-plans.index')->with('error', '這個計畫的設備已淘汰或停用，不能再建立保養工單。');
        }

        // 建立計畫目前這一期（排定日＝下次到期日）的工單；這一期已經有工單（連按兩次、兩個分頁各按一次）就不重複建立。
        $order = DB::transaction(function () use ($maintenancePlan, $generator) {
            $plan = MaintenancePlan::query()->whereKey($maintenancePlan->id)->lockForUpdate()->first();   // 鎖住這個計畫，兩個請求不會同時通過下面的檢查

            return $generator->createForCurrentCycle($plan);
        });

        if ($order === null) {
            return redirect()->route('maintenance-orders.index')->with('error', '這個計畫在 ' . $maintenancePlan->next_due_date?->toDateString() . ' 已經有保養工單，不重複建立。');
        }

        return redirect()->route('maintenance-orders.index')->with('success', '已依保養計畫「'.$maintenancePlan->name.'」建立保養工單 ' . $order->displayNumber());
    }

    /**
     * 修改保養工單（GET /maintenance-orders/{id}/edit）：只能改「排定保養日期」，需要 maintenance.manage。
     * 已完成（已有保養結果）的工單不能再改。
     */
    public function edit(MaintenanceOrder $maintenanceOrder): View|RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_ABORTED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', $this->finalMessage($maintenanceOrder, '修改'));
        }
        if ($maintenanceOrder->result()->exists() || in_array($maintenanceOrder->status, [MaintenanceOrder::STATUS_COMPLETED, MaintenanceOrder::STATUS_CONVERTED], true)) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已回報結果的保養工單不能修改。');
        }

        return view('maintenance_orders.edit', compact('maintenanceOrder'));
    }

    /** 儲存修改（PUT /maintenance-orders/{id}）：更新排定日期並寫操作紀錄。 */
    public function update(Request $request, MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_ABORTED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', $this->finalMessage($maintenanceOrder, '修改'));
        }
        if ($maintenanceOrder->result()->exists() || in_array($maintenanceOrder->status, [MaintenanceOrder::STATUS_COMPLETED, MaintenanceOrder::STATUS_CONVERTED], true)) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已回報結果的保養工單不能修改。');
        }

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
        ], [
            'scheduled_date.required' => '請選擇排定保養日期。',
            'scheduled_date.date' => '排定保養日期格式不正確。',
        ]);

        $old = $maintenanceOrder->scheduled_date?->toDateString();
        $maintenanceOrder->update($validated);
        $new = $maintenanceOrder->scheduled_date?->toDateString();

        if ($old !== $new) {
            AuditLogger::log('updated', $maintenanceOrder, ['scheduled_date' => ['old' => $old, 'new' => $new]], '修改保養工單 #' . $maintenanceOrder->id . ' 的排定日期');
        }

        return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('success', '保養工單已修改。');
    }

    /**
     * 取消（刪除）保養工單（DELETE /maintenance-orders/{id}）：取消與刪除是同一件事，做法是軟刪除，
     * 列表與統計不再列出，資料與操作紀錄仍保留；排程不會因此重新產生同一期的工單。
     * 已完成或已經回報保養結果的工單不能取消（結果與用料已經記錄）。
     */
    public function destroy(MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_ABORTED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', $this->finalMessage($maintenanceOrder, '取消'));
        }
        if ($maintenanceOrder->result()->exists() || in_array($maintenanceOrder->status, [MaintenanceOrder::STATUS_COMPLETED, MaintenanceOrder::STATUS_CONVERTED], true)) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成或已回報保養結果的工單不能取消。');
        }

        // 取消＝改成「已中止」（保留紀錄、唯讀；列表平常不顯示，篩選「已中止」才看得到）。
        $oldStatus = $maintenanceOrder->status;
        $maintenanceOrder->update(['status' => MaintenanceOrder::STATUS_ABORTED]);

        // 取消的是計畫目前這一期的工單：到期日往後推一個週期，不然這個計畫會因為「這一期已經產生過」而永遠停住。
        // 新的到期日已經在 3 天內就馬上產生下一張（跟回報結果一樣）。
        if ($plan = $maintenanceOrder->maintenancePlan) {
            $plan->advanceDueDateAfter($maintenanceOrder);
            app(\App\Services\DueMaintenanceOrderGenerator::class)->generateForPlan($plan);
        }

        AuditLogger::log('status_changed', $maintenanceOrder, ['status' => ['old' => $oldStatus, 'new' => MaintenanceOrder::STATUS_ABORTED]], '取消保養工單 ' . $maintenanceOrder->displayNumber() . '（改為已中止）');

        return $this->backToList('maintenance-orders.index')->with('success', '保養工單 ' . $maintenanceOrder->displayNumber() . ' 已取消，改為「已中止」（在篩選選「已中止」可以查看）。');
    }

    /**
     * 確認派工／改負責人（PATCH /maintenance-orders/{id}/assign）：需要 maintenance.manage。
     * - 待處理：選好負責人按「確認派工」，狀態改成進行中（負責人才能回報結果）。
     * - 進行中：可以改派給別人（負責人必填，不能取消派工）。
     * - 已轉報修、已完成、已中止：不能再改。
     * 負責人必須是「有保養回報權限、帳號啟用中」的人。
     */
    public function assign(Request $request, MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_ABORTED) {
            return back()->with('error', $this->finalMessage($maintenanceOrder, '派工'));
        }
        if (! in_array($maintenanceOrder->status, [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS], true)) {
            return back()->with('error', '已回報結果的保養工單不能再派工。');
        }

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::in($this->assignableUsers()->pluck('id')->all())],
        ], [
            'assigned_to.required' => '請選擇負責人。',
            'assigned_to.in' => '只能指派給有「保養回報」權限、帳號啟用中的人。',
        ]);

        $oldAssignee = $maintenanceOrder->assigned_to;
        $oldStatus = $maintenanceOrder->status;
        $maintenanceOrder->update(['assigned_to' => (int) $validated['assigned_to'], 'status' => MaintenanceOrder::STATUS_IN_PROGRESS]);
        $maintenanceOrder->load('assignee');

        $dispatched = $oldStatus === MaintenanceOrder::STATUS_PENDING;
        if ($dispatched || $oldAssignee !== $maintenanceOrder->assigned_to) {
            AuditLogger::log('assigned', $maintenanceOrder, [
                'assigned_to' => ['old' => $oldAssignee, 'new' => $maintenanceOrder->assigned_to],
                'status' => ['old' => $oldStatus, 'new' => $maintenanceOrder->status],
            ], ($dispatched ? '確認派工' : '改派') . '保養工單 ' . $maintenanceOrder->displayNumber() . '：' . $maintenanceOrder->assignee?->name);
        }

        return back()->with('success', $dispatched ? '已確認派工，狀態改為進行中。' : '已改派負責人。');
    }

    /** 已完成或已中止的工單不能再做某個動作時，顯示給使用者的說明。 */
    private function finalMessage(MaintenanceOrder $order, string $action): string
    {
        return $order->status === MaintenanceOrder::STATUS_ABORTED
            ? "這張保養工單已中止（取消或設備淘汰），資料已定案，不能{$action}。"
            : "已完成的保養工單不能{$action}。";
    }

    /**
     * 可以被指派的人：有 maintenance.report 權限（或系統管理員，永遠全開）、帳號啟用中。
     * 用一次資料庫查詢取出，不逐一用戶判斷權限（用戶多了會變很慢）。
     */
    private function assignableUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('role', fn ($role) => $role->where('role_code', 'admin')
                ->orWhere(fn ($other) => $other->withPermission('maintenance.report')))
            ->orderBy('name')
            ->get();
    }
}
