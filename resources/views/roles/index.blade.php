@extends('layouts.app')

@section('title', __('roles.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-shield-lock me-2"></i>{{ __('roles.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('roles.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('roles.add_role') }}
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('roles.index') }}" class="filter-bar">
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('roles.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('roles.filter.keyword_placeholder') }}">
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    @if (request('keyword'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('roles.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($roles->isEmpty())
        <div class="alert alert-info mb-0">{{ __('roles.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('roles.table.name') }}</th>
                            <th>{{ __('roles.table.description') }}</th>
                            <th class="text-center">{{ __('roles.table.users') }}</th>
                            <th class="text-center">{{ __('roles.table.permissions') }}</th>
                            <th class="text-center">{{ __('roles.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $role->name }}</span>
                                    @if ($role->is_system)
                                        <span class="badge text-bg-info ms-1">{{ __('roles.system_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $role->description ?? '—' }}</td>
                                <td class="text-center">{{ $role->users_count }}</td>
                                <td class="text-center">
                                    @if ($role->isAdmin())
                                        <span class="badge text-bg-primary">{{ __('roles.admin_all_permissions') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('roles.permission_count', ['count' => count($role->permissions ?? [])]) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('roles.edit', $role) }}"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                            onsubmit="return confirm('{{ __('roles.confirm.delete', ['name' => $role->name]) }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" @disabled($role->is_system)
                                                title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $roles->links() }}
        </div>
    @endif
@endsection
