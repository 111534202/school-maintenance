<?php

namespace App\Http\Controllers;

use App\Exports\TableExport;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MaintenanceOrderController extends Controller
{
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

        return view('maintenance_orders.index', compact('orders', 'assignees'));
    }

    /** 工單列表的查詢（列表與匯出共用）：套用網址上的搜尋與篩選條件，並依狀態、排定日排好順序。 */
    private function indexQuery(Request $request)
    {
        return MaintenanceOrder::query()
            ->with(['maintenancePlan', 'result', 'device', 'assignee'])
            // 期限：overdue＝已逾期、week＝一週內到期（含已逾期）；只看自己負責的用 mine=1；assignee＝指定負責人（none＝還沒指派）。
            ->when($request->string('due')->toString() === 'overdue', fn ($query) => $query->overdue())
            ->when($request->string('due')->toString() === 'week', fn ($query) => $query->dueWithin(7))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($request->filled('assignee'), fn ($query) => $request->string('assignee')->toString() === 'none' ? $query->whereNull('assigned_to') : $query->where('assigned_to', $request->integer('assignee')))
            // 關鍵字搜尋：設備（編號、資產編號、品牌、型號）、來源計畫名稱、設備類別名稱、負責人姓名；輸入 12 或 #12 找工單編號。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = trim($request->string('keyword')->toString());
                $like = '%' . $keyword . '%';
                $query->where(function ($q) use ($like, $keyword) {
                    $q->whereHas('device', fn ($device) => $device->where('device_code', 'like', $like)->orWhere('asset_code', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial_number', 'like', $like))
                        ->orWhereHas('maintenancePlan', fn ($plan) => $plan->where('name', 'like', $like))
                        ->orWhereHas('deviceCategory', fn ($category) => $category->where('name', 'like', $like))
                        ->orWhereHas('assignee', fn ($user) => $user->where('name', 'like', $like));
                    if (ctype_digit(ltrim($keyword, '#'))) {
                        $q->orWhere('maintenance_orders.id', (int) ltrim($keyword, '#'));
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('scheduled_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('scheduled_date', '<=', $request->date('to')))
            // 排序：
            // 1. 待處理排最前面，進行中其次，已完成排最後。
            // 2. 還沒完成的（待處理、進行中）：排定日期近的在上 → 同一天建立得早的在上 → 再依來源計畫名稱 A→Z。
            // 3. 已完成的：排定日期遠的（最新的）在上 → 同一天建立得晚的在上（早的在下）→ 再依來源計畫名稱 A→Z。
            // 最後用工單編號，讓完全相同的幾筆每次順序都一樣。
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [MaintenanceOrder::STATUS_PENDING, MaintenanceOrder::STATUS_IN_PROGRESS])
            ->orderByRaw('CASE WHEN status <> ? THEN scheduled_date END ASC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status = ? THEN scheduled_date END DESC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status <> ? THEN created_at END ASC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderByRaw('CASE WHEN status = ? THEN created_at END DESC', [MaintenanceOrder::STATUS_COMPLETED])
            ->orderBy(MaintenancePlan::select('name')->whereColumn('maintenance_plans.id', 'maintenance_orders.maintenance_plan_id'))
            ->orderBy('maintenance_orders.id');
    }

    /**
     * 匯出 Excel（GET /maintenance-orders/export）：匯出「目前搜尋與篩選條件」下的全部保養工單（不分頁），
     * 含保養結果（OK／NG、執行人、轉報修單號）；寫一筆操作紀錄。
     */
    public function export(Request $request)
    {
        $rows = $this->indexQuery($request)->with(['result.executor', 'deviceCategory', 'device.category'])->get()->map(function (MaintenanceOrder $o) {
            $result = $o->result;

            return [
                $o->id,
                $o->device?->device_code,
                $o->device ? trim(($o->device->category?->name ?? '') . ' ' . $o->device->brand . ' ' . $o->device->model) : null,
                $o->categoryName(),
                $o->maintenancePlan?->name ?? ($o->source === MaintenanceOrder::SOURCE_AI ? 'AI 預防保養（無計畫）' : null),
                $o->sourceLabel(),
                $o->statusLabel()[0],
                $o->assignee?->name,
                $o->scheduled_date?->format('Y-m-d'),
                $o->isOverdue() ? '是' : '否',
                $result ? ($result->isNg() ? 'NG 異常' : 'OK 正常') : null,
                $result?->executor?->name,
                $result?->executed_at?->format('Y-m-d H:i'),
                $result?->note,
                $result?->repair_request_id,
                $o->created_at?->format('Y-m-d H:i'),
            ];
        })->all();

        $file = '保養工單-' . now()->format('Ymd-His') . '.xlsx';
        AuditLogger::log('downloaded', null, ['file' => $file, 'rows' => count($rows)], "匯出保養工單 {$file}（" . count($rows) . ' 筆）');

        return Excel::download(new TableExport('保養工單', ['工單編號', '設備編號', '設備（類別 品牌 型號）', '設備類別', '來源計畫', '來源', '狀態', '負責人', '排定保養日期', '逾期', '保養結果', '執行人', '執行時間', '備註', '轉報修單號', '建立時間'], $rows), $file);
    }

    /**
     * 保養工單詳細頁（第 1 週任務 6；第 2 週起一併顯示保養結果）。
     */
    public function show(MaintenanceOrder $maintenanceOrder): View
    {
        $maintenanceOrder->load('maintenancePlan.maintenanceItems', 'result', 'device', 'assignee');

        // 「指派負責人」下拉選單：有保養回報權限、帳號啟用中的人。
        $assignees = $this->assignableUsers();

        return view('maintenance_orders.show', compact('maintenanceOrder', 'assignees'));
    }

    /**
     * 由保養計畫人工建立一筆保養工單（第 1 週任務 5）。
     * 本週僅做「人工由計畫建單」，不做排程自動產生；
     * 設備類別、排定保養日期直接沿用來源計畫的資料，驗證資料鏈路可以串起來即可。
     */
    public function storeFromPlan(MaintenancePlan $maintenancePlan): RedirectResponse
    {
        abort_unless($maintenancePlan->is_active, 422, '已停用的保養計畫不能建立工單');

        MaintenanceOrder::create([
            'maintenance_plan_id' => $maintenancePlan->id,
            'device_id' => $maintenancePlan->device_id,
            'device_category_id' => $maintenancePlan->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => $maintenancePlan->next_due_date,
        ]);

        return redirect()->route('maintenance-orders.index')->with('success', '已依保養計畫「'.$maintenancePlan->name.'」建立保養工單');
    }

    /**
     * 修改保養工單（GET /maintenance-orders/{id}/edit）：只能改「排定保養日期」，需要 maintenance.manage。
     * 已完成（已有保養結果）的工單不能再改。
     */
    public function edit(MaintenanceOrder $maintenanceOrder): View|RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成的保養工單不能修改。');
        }

        return view('maintenance_orders.edit', compact('maintenanceOrder'));
    }

    /** 儲存修改（PUT /maintenance-orders/{id}）：更新排定日期並寫操作紀錄。 */
    public function update(Request $request, MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成的保養工單不能修改。');
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
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED || $maintenanceOrder->result()->exists()) {
            return redirect()->route('maintenance-orders.show', $maintenanceOrder)->with('error', '已完成或已回報保養結果的工單不能取消。');
        }

        $id = $maintenanceOrder->id;
        $maintenanceOrder->delete();

        // 取消的是計畫目前這一期的工單：到期日往後推一個週期，不然這個計畫會因為「這一期已經產生過」而永遠停住。
        $maintenanceOrder->maintenancePlan?->advanceDueDateAfter($maintenanceOrder);

        AuditLogger::log('deleted', $maintenanceOrder, ['status' => $maintenanceOrder->status], '取消（刪除）保養工單 #' . $id);

        return redirect()->route('maintenance-orders.index')->with('success', '保養工單 #' . $id . ' 已取消。');
    }

    /**
     * 指派負責人（PATCH /maintenance-orders/{id}/assign）：需要 maintenance.manage。
     * 負責人必須是「有保養回報權限、帳號啟用中」的人；已完成的工單不能再改；留空＝取消指派（回到有回報權限的人都能回報）。
     */
    public function assign(Request $request, MaintenanceOrder $maintenanceOrder): RedirectResponse
    {
        if ($maintenanceOrder->status === MaintenanceOrder::STATUS_COMPLETED) {
            return back()->with('error', '已完成的保養工單不能再指派負責人。');
        }

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::in($this->assignableUsers()->pluck('id')->all())],
        ], [
            'assigned_to.in' => '只能指派給有「保養回報」權限、帳號啟用中的人。',
        ]);

        $old = $maintenanceOrder->assigned_to;
        $maintenanceOrder->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        if ($old !== $maintenanceOrder->assigned_to) {
            AuditLogger::log('assigned', $maintenanceOrder, ['assigned_to' => ['old' => $old, 'new' => $maintenanceOrder->assigned_to]],
                '指派保養工單 #' . $maintenanceOrder->id . ' 的負責人' . ($maintenanceOrder->assignee ? '：' . $maintenanceOrder->assignee->name : '（取消指派）'));
        }

        return back()->with('success', $maintenanceOrder->assigned_to ? '已指派負責人。' : '已取消指派。');
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
