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
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $loggableTypes = AuditLog::query()->select('loggable_type')->whereNotNull('loggable_type')->distinct()->pluck('loggable_type');

        return view('audit-logs.index', compact('logs', 'users', 'actions', 'loggableTypes'));
    }
}
