<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** 操作紀錄查詢：可依使用者、事件、對象類型、日期範圍與說明關鍵字篩選（需要 audit-logs.view 權限）。 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::with('user')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('loggable_type'), fn ($query) => $query->where('loggable_type', $request->string('loggable_type')))
            ->when($request->filled('keyword'), fn ($query) => $query->where('description', 'like', '%' . $request->string('keyword') . '%'))
            ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $request->date('date_to')->endOfDay()))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $users = User::withTrashed()->orderBy('name')->get();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $loggableTypes = AuditLog::query()->select('loggable_type')->whereNotNull('loggable_type')->distinct()->pluck('loggable_type');

        return view('audit-logs.index', compact('logs', 'users', 'actions', 'loggableTypes'));
    }
}
