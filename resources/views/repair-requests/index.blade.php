@extends('layouts.app')

@section('title', '維修案件看板')

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
        <h1>維修案件看板</h1>
        <a class="btn btn-primary" href="{{ route('repair-requests.create') }}">＋ 新增報修</a>
    </div>

    <form method="GET" action="{{ route('repair-requests.index') }}" style="display:flex; gap:0.5rem; margin-bottom:1rem; align-items:flex-end;">
        <div class="field" style="margin-bottom:0;">
            <label for="status">狀態</label>
            <select id="status" name="status">
                <option value="">全部</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                        {{ $statusOption->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label for="location">教室／地點</label>
            <input type="text" id="location" name="location" value="{{ request('location') }}" placeholder="例如：A101">
        </div>
        <button class="btn btn-secondary" type="submit">篩選</button>
        @if (request('status') || request('location'))
            <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">清除篩選</a>
        @endif
    </form>

    @if ($repairRequests->isEmpty())
        <p>目前沒有符合條件的報修案件。</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>標題</th>
                    <th>設備／地點</th>
                    <th>影響程度</th>
                    <th>影響上課</th>
                    <th>狀態</th>
                    <th>維修人員</th>
                    <th>送出時間</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($repairRequests as $repairRequest)
                    <tr>
                        <td><a href="{{ route('repair-requests.show', $repairRequest) }}">{{ $repairRequest->title }}</a></td>
                        <td>{{ $repairRequest->device_note ?? $repairRequest->location ?? '未填寫' }}</td>
                        <td>
                            @php($impactLabel = ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'][$repairRequest->impact_level] ?? $repairRequest->impact_level)
                            <span class="badge {{ $repairRequest->impact_level === 'high' ? 'badge-off' : 'badge-on' }}">{{ $impactLabel }}</span>
                        </td>
                        <td>{{ $repairRequest->affects_class ? '是' : '否' }}</td>
                        <td>
                            <span class="badge" style="background: {{ $statusColors[$repairRequest->status->value] }};">
                                {{ $repairRequest->status->label() }}
                            </span>
                        </td>
                        <td>{{ $repairRequest->assignee_note ?? '未指派' }}</td>
                        <td>{{ $repairRequest->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 1rem;">
            {{ $repairRequests->links() }}
        </div>
    @endif
@endsection
