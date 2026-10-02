@extends('layouts.app')

@section('title', __('repair_requests.board_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-tools me-2"></i>{{ __('repair_requests.board_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('repairs.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('repair_requests.add_request') }}
        </a>
    </div>

    {{-- 篩選列：全部放同一排、不換行（視窗太窄時整列左右捲動），按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('repairs.index') }}" class="filter-bar">
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('repair_requests.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">{{ __('repair_requests.filter.status_all') }}</option>
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                                {{ $statusOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="location" class="form-label small mb-1">{{ __('repair_requests.filter.location') }}</label>
                    <input type="text" id="location" name="location" class="form-control form-control-sm" style="width: 11rem;"
                        value="{{ request('location') }}" placeholder="{{ __('repair_requests.filter.location_placeholder') }}">
                </div>
                <div>
                    <label for="assignee" class="form-label small mb-1">{{ __('repair_requests.filter.assignee') }}</label>
                    <input type="text" id="assignee" name="assignee" class="form-control form-control-sm" style="width: 11rem;"
                        value="{{ request('assignee') }}" placeholder="{{ __('repair_requests.filter.assignee_placeholder') }}">
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    @if (request('status') || request('location') || request('assignee'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('repairs.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($repairRequests->isEmpty())
        <div class="alert alert-info mb-0">{{ __('repair_requests.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('repair_requests.table.title') }}</th>
                            <th>{{ __('repair_requests.table.device_location') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.impact_level') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.affects_class') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.status') }}</th>
                            <th>{{ __('repair_requests.table.assignee') }}</th>
                            <th>{{ __('repair_requests.table.submitted_at') }}</th>
                            <th>{{ __('repair_requests.table.waiting_time') }}</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($repairRequests as $repairRequest)
                            @php
                                $impactBadge = ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repairRequest->impact_level] ?? 'text-bg-secondary';
                            @endphp
                            <tr>
                                <td><a href="{{ route('repairs.show', $repairRequest) }}" class="text-decoration-none">{{ $repairRequest->title }}</a></td>
                                <td>{{ $repairRequest->device->device_code ?? $repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $impactBadge }}">{{ __('repair_requests.impact_level.' . $repairRequest->impact_level) }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($repairRequest->affects_class)
                                        <span class="badge text-bg-danger">{{ __('repair_requests.yes') }}</span>
                                    @else
                                        <span class="badge text-bg-light border text-muted">{{ __('repair_requests.no') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">@include('repairs._status-badge', ['status' => $repairRequest->status])</td>
                                <td>
                                    {{-- 讓主管看到「這個人手上還有幾件沒結案」，跟名字放在同一格，方便判斷要不要再加派；
                                         系統不會自動幫忙排序或推薦人選。 --}}
                                    {{ $repairRequest->assignedTechnician->name ?? $repairRequest->assignee_note ?? __('repair_requests.unassigned') }}
                                    @if ($repairRequest->assigned_to && ($activeCaseCountsByAssignee[$repairRequest->assigned_to] ?? 0) > 0)
                                        <span class="text-muted small">{{ __('repair_requests.active_case_suffix', ['count' => $activeCaseCountsByAssignee[$repairRequest->assigned_to]]) }}</span>
                                    @endif
                                </td>
                                <td>{{ $repairRequest->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $repairRequest->created_at->diffForHumans() }}</td>
                                <td class="text-center">
                                    <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('repairs.show', $repairRequest) }}"
                                        title="{{ __('common.buttons.view') }}" aria-label="{{ __('common.buttons.view') }}"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $repairRequests->links() }}
        </div>
    @endif
@endsection
