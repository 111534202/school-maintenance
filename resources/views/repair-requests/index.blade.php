@extends('layouts.app')

@section('title', __('repair_requests.board_title'))

@php
    $statusColors = [
        'pending' => '#eceff1',
        'assigned' => '#e3f2fd',
        'in_progress' => '#fff7e6',
        'pending_review' => '#fde8e8',
        'completed' => '#e3f9e5',
    ];
@endphp

@section('content')
    <div class="toolbar">
        <h1>{{ __('repair_requests.board_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('repair-requests.create') }}">{{ __('repair_requests.add_request') }}</a>
    </div>

    <form method="GET" action="{{ route('repair-requests.index') }}" class="filter-form">
        <div class="field">
            <label for="status">{{ __('repair_requests.filter.status') }}</label>
            <select id="status" name="status">
                <option value="">{{ __('repair_requests.filter.status_all') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                        {{ $statusOption->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="location">{{ __('repair_requests.filter.location') }}</label>
            <input type="text" id="location" name="location" value="{{ request('location') }}" placeholder="{{ __('repair_requests.filter.location_placeholder') }}">
        </div>
        <div class="field">
            <label for="assignee">{{ __('repair_requests.filter.assignee') }}</label>
            <input type="text" id="assignee" name="assignee" value="{{ request('assignee') }}" placeholder="{{ __('repair_requests.filter.assignee_placeholder') }}">
        </div>
        <button class="btn btn-secondary" type="submit">{{ __('common.buttons.filter') }}</button>
        @if (request('status') || request('location') || request('assignee'))
            <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">{{ __('common.buttons.clear_filter') }}</a>
        @endif
    </form>

    @if ($repairRequests->isEmpty())
        <p>{{ __('repair_requests.empty_list') }}</p>
    @else
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>{{ __('repair_requests.table.title') }}</th>
                    <th>{{ __('repair_requests.table.device_location') }}</th>
                    <th>{{ __('repair_requests.table.impact_level') }}</th>
                    <th>{{ __('repair_requests.table.affects_class') }}</th>
                    <th>{{ __('repair_requests.table.status') }}</th>
                    <th>{{ __('repair_requests.table.assignee') }}</th>
                    <th>{{ __('repair_requests.table.submitted_at') }}</th>
                    <th>{{ __('repair_requests.table.waiting_time') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($repairRequests as $repairRequest)
                    <tr>
                        <td><a href="{{ route('repair-requests.show', $repairRequest) }}">{{ $repairRequest->title }}</a></td>
                        <td>{{ $repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled') }}</td>
                        <td>
                            @php($impactLabel = __('repair_requests.impact_level.' . $repairRequest->impact_level))
                            <span class="badge {{ $repairRequest->impact_level === 'high' ? 'badge-off' : 'badge-on' }}">{{ $impactLabel }}</span>
                        </td>
                        <td>{{ $repairRequest->affects_class ? __('repair_requests.yes') : __('repair_requests.no') }}</td>
                        <td>
                            <span class="badge" style="background: {{ $statusColors[$repairRequest->status->value] }};">
                                {{ $repairRequest->status->label() }}
                            </span>
                        </td>
                        <td>
                            {{-- 讓主管看到「這個人手上還有幾件沒結案」，跟名字放在同一行、用括號附註，
                                 不要換行，方便自己判斷要不要再加派給他；系統不會自動幫忙排序或推薦人選。 --}}
                            {{ $repairRequest->assignee_note ?? __('repair_requests.unassigned') }}
                            @if ($repairRequest->assignee_note && ($activeCaseCountsByAssignee[$repairRequest->assignee_note] ?? 0) > 0)
                                <span style="color:#616e7c;">{{ __('repair_requests.active_case_suffix', ['count' => $activeCaseCountsByAssignee[$repairRequest->assignee_note]]) }}</span>
                            @endif
                        </td>
                        <td>{{ $repairRequest->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $repairRequest->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $repairRequests->links() }}
        </div>
    @endif
@endsection
