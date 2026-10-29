<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\AuditLog;       // 操作紀錄資料表的模型
use App\Models\User;           // 用戶資料表的模型（篩選列的「使用者」下拉選單要用）
use Illuminate\Http\Request;   // 這一次瀏覽器送來的請求

/**
 * 操作紀錄查詢：可依使用者、事件、對象類型、日期範圍與說明關鍵字篩選（需要 audit-logs.view 權限）。
 * 只能「看」，不能新增、修改或刪除；紀錄是由各功能在操作當下透過 AuditLogger 自動寫入的。
 * 【想讓某個新功能也留下紀錄】在該功能的 Controller 呼叫 AuditLogger::log('動作代碼', $資料)，
 *  再到 lang/各語言資料夾/audit.php 補上動作的中文／英文名稱即可，這支 Controller 不用改。
 */
class AuditLogController extends Controller
{
    /** 操作紀錄列表（GET /audit-logs），每頁 30 筆，最新的排最前面。 */
    public function index(Request $request)
    {
        // 日期欄位如果有填，必須是正確的日期格式；亂填會導回並顯示錯誤。
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        // 每個 when() 都是「有填這個篩選條件才套用」，沒填就略過。
        $logs = AuditLog::with('user')   // with('user')：順便查好操作者，避免每一列都多查一次資料庫
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('loggable_type'), fn ($query) => $query->where('loggable_type', $request->string('loggable_type')))
            // 說明關鍵字：like %字% 代表「說明裡包含這段字」。
            ->when($request->filled('keyword'), fn ($query) => $query->where('description', 'like', '%' . $request->string('keyword') . '%'))
            // 日期範圍：起日從當天 00:00:00 起算，迄日算到當天 23:59:59，這樣選同一天也查得到整天的紀錄。
            ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $request->date('date_to')->endOfDay()))
            ->orderByDesc('id')   // 編號越大越新，所以由大到小 = 最新在最上面
            ->paginate(30)
            ->withQueryString();  // 換頁時保留篩選條件

        // 篩選列「使用者」下拉選單：withTrashed 連已被刪除的帳號也列出，才查得到他們過去的操作。
        $users = User::withTrashed()->orderBy('name')->get();
        // 「事件」下拉選單：只列出紀錄裡真的出現過的事件（distinct = 去除重複）。
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        // 「對象類型」下拉選單：同理，只列出出現過的類型；沒有對象的紀錄（例如登入）不列入。
        $loggableTypes = AuditLog::query()->select('loggable_type')->whereNotNull('loggable_type')->distinct()->pluck('loggable_type');

        return view('audit-logs.index', compact('logs', 'users', 'actions', 'loggableTypes'));
    }
}
