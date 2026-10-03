{{-- 操作紀錄頁（對應 AuditLogController::index）：誰、何時、對什麼做了什麼。只能查看，不能修改或刪除。 --}}
{{-- 紀錄由各功能呼叫 App\Services\AuditLogger 自動寫入；事件與類型的中文名稱在 lang/各語言資料夾/audit.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('audit.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-clock-history me-2"></i>{{ __('audit.index_title') }}</h1>
    </div>

    {{-- 篩選列：全部放同一排、不換行（視窗太窄時整列左右捲動），按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上；filter-bar 樣式讓欄位固定單行、不換行。 --}}
            <form method="GET" action="{{ route('audit-logs.index') }}" class="filter-bar">
                <div>
                    {{-- 使用者下拉選單：包含已被刪除的帳號（才查得到他們過去的操作）。 --}}
                    <label for="user_id" class="form-label small mb-1">{{ __('audit.filter.user') }}</label>
                    <select id="user_id" name="user_id" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('audit.filter.user_all') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- 事件下拉選單：只列出紀錄裡真的出現過的事件，名稱以中文顯示。 --}}
                    <label for="action" class="form-label small mb-1">{{ __('audit.filter.action') }}</label>
                    <select id="action" name="action" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('audit.filter.action_all') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ \App\Services\AuditLogger::label('audit.actions.' . $action, $action) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- 對象類型下拉選單：用戶、設備、報修單…（只列出出現過的）。 --}}
                    <label for="loggable_type" class="form-label small mb-1">{{ __('audit.filter.type') }}</label>
                    <select id="loggable_type" name="loggable_type" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('audit.filter.type_all') }}</option>
                        @foreach ($loggableTypes as $type)
                            <option value="{{ $type }}" @selected(request('loggable_type') === $type)>{{ \App\Services\AuditLogger::label('audit.types.' . class_basename($type), class_basename($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- 日期範圍：起日從當天 00:00 起算，迄日算到當天 23:59:59。 --}}
                    <label for="date_from" class="form-label small mb-1">{{ __('audit.filter.date_from') }}</label>
                    <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" style="width: 9.5rem;" value="{{ request('date_from') }}">
                </div>
                <div>
                    <label for="date_to" class="form-label small mb-1">{{ __('audit.filter.date_to') }}</label>
                    <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" style="width: 9.5rem;" value="{{ request('date_to') }}">
                </div>
                <div>
                    {{-- 說明關鍵字：比對紀錄的說明文字。 --}}
                    <label for="keyword" class="form-label small mb-1">{{ __('audit.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 12rem;" value="{{ request('keyword') }}">
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    {{-- 只要有套用任何篩選條件，就顯示「清除篩選」按鈕。 --}}
                    @if (request()->hasAny(['user_id', 'action', 'loggable_type', 'date_from', 'date_to', 'keyword']))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('audit-logs.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('audit.table.time') }}</th>
                        <th>{{ __('audit.table.user') }}</th>
                        <th class="text-center">{{ __('audit.table.action') }}</th>
                        <th>{{ __('audit.table.description') }}</th>
                        <th>{{ __('audit.table.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出紀錄；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($logs as $log)
                        @php
                            // 新寫入的紀錄都有說明；舊紀錄（沒有說明）退回用「類型 #編號」。
                            // 說明欄：新寫入的紀錄都有說明；舊紀錄沒有就退回顯示「類型 #編號」。
                            $description = $log->description
                                ?: ($log->loggable_type
                                    ? \App\Services\AuditLogger::label('audit.types.' . class_basename($log->loggable_type), class_basename($log->loggable_type)) . ' #' . $log->loggable_id
                                    : '—');
                            // 事件徽章顏色：刪除／失敗／退回為紅；新增／還原為綠；登入登出為淺灰；密碼重設／狀態變更為黃；其他為藍。
                            $badge = match ($log->action) {
                                'deleted', 'login_failed', 'rejected' => 'text-bg-danger',
                                'created', 'restored' => 'text-bg-success',
                                'login', 'logout' => 'text-bg-light border text-dark',
                                'password_reset', 'status_changed' => 'text-bg-warning',
                                default => 'text-bg-primary',
                            };
                        @endphp
                        <tr>
                            {{-- 紀錄時間（已依台北時區儲存與顯示）。 --}}
                            <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            {{-- 操作者姓名；沒有登入者（例如登入失敗）顯示「系統」。 --}}
                            <td class="text-nowrap">{{ $log->user->name ?? __('audit.system_user') }}</td>
                            <td class="text-center text-nowrap"><span class="badge {{ $badge }}">{{ \App\Services\AuditLogger::label('audit.actions.' . $log->action, $log->action) }}</span></td>
                            <td>{{ $description }}</td>
                            <td class="small text-muted">
                                @if ($log->changes)
                                    {{-- 詳細欄：把變更內容（JSON）原樣顯示；JSON_UNESCAPED_UNICODE 讓中文直接顯示，不變成 \u 開頭的編碼。 --}}
                                    <code>{{ json_encode($log->changes, JSON_UNESCAPED_UNICODE) }}</code>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('audit.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 分頁按鈕（每頁 30 筆）。 --}}
    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
