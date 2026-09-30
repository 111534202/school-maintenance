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

    <form method="GET" action="{{ route('repair-requests.index') }}" class="filter-form">
        <div class="field">
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
        <div class="field">
            <label for="location">教室／地點</label>
            <input type="text" id="location" name="location" value="{{ request('location') }}" placeholder="例如：A101">
        </div>
        <div class="field">
            <label for="assignee">維修人員</label>
            <input type="text" id="assignee" name="assignee" value="{{ request('assignee') }}" placeholder="例如：王小明">
        </div>
        <button class="btn btn-secondary" type="submit">篩選</button>
        @if (request('status') || request('location') || request('assignee'))
            <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">清除篩選</a>
        @endif
    </form>

    @if ($repairRequests->isEmpty())
        <p>目前沒有符合條件的報修案件。</p>
    @else
        <div class="table-scroll">
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
                    <th>等待多久</th>
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
                        <td>
                            {{-- 讓主管看到「這個人手上還有幾件沒結案」，跟名字放在同一行、用括號附註，
                                 不要換行，方便自己判斷要不要再加派給他；系統不會自動幫忙排序或推薦人選。 --}}
                            {{ $repairRequest->assignee_note ?? '未指派' }}
                            @if ($repairRequest->assignee_note && ($activeCaseCountsByAssignee[$repairRequest->assignee_note] ?? 0) > 0)
                                <span style="color:#616e7c;">（未結案 {{ $activeCaseCountsByAssignee[$repairRequest->assignee_note] }} 件）</span>
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
