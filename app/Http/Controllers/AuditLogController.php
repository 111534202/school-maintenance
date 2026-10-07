<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('loggable_type'), fn ($query) => $query->where('loggable_type', $request->string('loggable_type')))
            // 時間區間查詢：date_from/date_to 都是純日期（yyyy-mm-dd），date_to 要含當天整天
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $loggableTypes = AuditLog::query()->select('loggable_type')->whereNotNull('loggable_type')->distinct()->pluck('loggable_type');

        return view('audit-logs.index', compact('logs', 'users', 'actions', 'loggableTypes'));
    }
}
