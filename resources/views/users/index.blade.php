@extends('layouts.app')

@section('title', __('users.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-people me-2"></i>{{ __('users.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('users.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('users.add_user') }}
        </a>
    </div>

    {{-- 篩選列：全部放同一排、不換行（視窗太窄時整列左右捲動），按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('users.index') }}" class="filter-bar">
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('users.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('users.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="role_id" class="form-label small mb-1">{{ __('users.filter.role') }}</label>
                    <select id="role_id" name="role_id" class="form-select form-select-sm" style="width: 11rem;">
                        <option value="">{{ __('users.filter.role_all') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_id" class="form-label small mb-1">{{ __('users.filter.department') }}</label>
                    <select id="department_id" name="department_id" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('users.filter.department_all') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('users.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('users.filter.status_all') }}</option>
                        @foreach (['active', 'inactive', 'deleted'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ __('users.status.' . $statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    @if (request('keyword') || request('role_id') || request('department_id') || request('status'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('users.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($users->isEmpty())
        <div class="alert alert-info mb-0">{{ __('users.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('users.table.username') }}</th>
                            <th>{{ __('users.table.name') }}</th>
                            <th>{{ __('users.table.email') }}</th>
                            <th>{{ __('users.table.phone') }}</th>
                            <th>{{ __('users.table.role') }}</th>
                            <th>{{ __('users.table.department') }}</th>
                            <th class="text-center">{{ __('users.table.status') }}</th>
                            <th>{{ __('users.table.last_login_at') }}</th>
                            <th class="text-center">{{ __('users.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($isSelf = $user->id === auth()->id())
                            <tr class="{{ $user->trashed() ? 'table-secondary' : '' }}">
                                <td>
                                    <span class="fw-semibold">{{ $user->username ?? '—' }}</span>
                                    @if ($isSelf)
                                        <span class="badge text-bg-info ms-1">{{ __('users.current_user_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '—' }}</td>
                                <td><span class="badge text-bg-primary">{{ $user->role->name ?? '—' }}</span></td>
                                <td>{{ $user->department->name ?? __('users.no_department') }}</td>
                                <td class="text-center">
                                    @if ($user->trashed())
                                        <span class="badge text-bg-danger">{{ __('users.status.deleted') }}</span>
                                    @elseif ($user->is_active)
                                        <span class="badge text-bg-success">{{ __('users.status.active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('users.status.inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        @if ($user->trashed())
                                            <form method="POST" action="{{ route('users.restore', $user) }}"
                                                onsubmit="return confirm('{{ __('users.confirm.restore', ['name' => $user->name]) }}');">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-success btn-sm icon-btn" type="submit"
                                                    title="{{ __('users.actions.restore') }}" aria-label="{{ __('users.actions.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('users.edit', $user) }}"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                            <form method="POST" action="{{ route('users.toggle', $user) }}"
                                                @if ($user->is_active) onsubmit="return confirm('{{ __('users.confirm.deactivate', ['name' => $user->name]) }}');" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-warning btn-sm icon-btn" type="submit" @disabled($isSelf)
                                                    title="{{ $user->is_active ? __('users.actions.deactivate') : __('users.actions.activate') }}"
                                                    aria-label="{{ $user->is_active ? __('users.actions.deactivate') : __('users.actions.activate') }}">
                                                    <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                onsubmit="return confirm('{{ __('users.confirm.delete', ['name' => $user->name]) }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" @disabled($isSelf)
                                                    title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    @endif
@endsection
