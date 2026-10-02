@extends('layouts.app')

@section('title', __('departments.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-diagram-3 me-2"></i>{{ __('departments.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('departments.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('departments.add_department') }}
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('departments.index') }}" class="filter-bar">
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('departments.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('departments.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('departments.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('departments.filter.status_all') }}</option>
                        @foreach (['active', 'inactive'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ __('departments.status.' . $statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    @if (request('keyword') || request('status'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('departments.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($departments->isEmpty())
        <div class="alert alert-info mb-0">{{ __('departments.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('departments.table.code') }}</th>
                            <th>{{ __('departments.table.name') }}</th>
                            <th>{{ __('departments.table.description') }}</th>
                            <th class="text-center">{{ __('departments.table.users') }}</th>
                            <th class="text-center">{{ __('departments.table.classrooms') }}</th>
                            <th class="text-center">{{ __('departments.table.status') }}</th>
                            <th class="text-center">{{ __('departments.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $department)
                            <tr>
                                <td>{{ $department->code ?? '—' }}</td>
                                <td class="fw-semibold">{{ $department->name }}</td>
                                <td>{{ $department->description ?? '—' }}</td>
                                <td class="text-center">{{ $department->users_count }}</td>
                                <td class="text-center">{{ $department->classrooms_count }}</td>
                                <td class="text-center">
                                    @if ($department->is_active)
                                        <span class="badge text-bg-success">{{ __('departments.status.active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('departments.status.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('departments.edit', $department) }}"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                        <form method="POST" action="{{ route('departments.toggle', $department) }}"
                                            @if ($department->is_active) onsubmit="return confirm('{{ __('departments.confirm.deactivate', ['name' => $department->name]) }}');" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-outline-warning btn-sm icon-btn" type="submit"
                                                title="{{ $department->is_active ? __('departments.actions.deactivate') : __('departments.actions.activate') }}"
                                                aria-label="{{ $department->is_active ? __('departments.actions.deactivate') : __('departments.actions.activate') }}">
                                                <i class="bi {{ $department->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('departments.destroy', $department) }}"
                                            onsubmit="return confirm('{{ __('departments.confirm.delete', ['name' => $department->name]) }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
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
            {{ $departments->links() }}
        </div>
    @endif
@endsection
