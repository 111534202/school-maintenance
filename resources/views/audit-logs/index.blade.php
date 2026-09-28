@extends('layouts.app')

@section('title', '操作紀錄查詢')

@section('content')
    <h3 class="mb-3">操作紀錄查詢</h3>

    <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <select name="user_id" class="form-select form-select-sm">
                <option value="">所有使用者</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="action" class="form-select form-select-sm">
                <option value="">所有事件</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="loggable_type" class="form-select form-select-sm">
                <option value="">所有對象類型</option>
                @foreach ($loggableTypes as $type)
                    <option value="{{ $type }}" @selected(request('loggable_type') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">查詢</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>時間</th>
                        <th>使用者</th>
                        <th>事件</th>
                        <th>對象</th>
                        <th>內容</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->user->name ?? '系統' }}</td>
                            <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                            <td>
                                @if ($log->loggable_type)
                                    {{ class_basename($log->loggable_type) }} #{{ $log->loggable_id }}
                                @else
                                    －
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $log->description }}
                                @if ($log->changes)
                                    <code>{{ json_encode($log->changes, JSON_UNESCAPED_UNICODE) }}</code>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">尚無符合條件的紀錄</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
