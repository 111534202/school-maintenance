@extends('layouts.app')

@section('title', '保養工單')

@section('content')
    <h1 class="h3 mb-3">保養工單</h1>

    <form method="GET" class="row row-cols-1 row-cols-sm-2 row-cols-md-auto g-2 align-items-end bg-white p-3 rounded shadow-sm mb-3">
        <div class="col">
            <label for="status" class="form-label small mb-1">狀態</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="pending" @selected(request('status') === 'pending')>待處理</option>
                <option value="in_progress" @selected(request('status') === 'in_progress')>進行中</option>
                <option value="completed" @selected(request('status') === 'completed')>已完成</option>
            </select>
        </div>
        <div class="col">
            <label for="source" class="form-label small mb-1">來源</label>
            <select name="source" id="source" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="periodic" @selected(request('source') === 'periodic')>定期</option>
                <option value="ai" @selected(request('source') === 'ai')>AI 辨識</option>
            </select>
        </div>
        <div class="col">
            <label for="from" class="form-label small mb-1">排定日期（起）</label>
            <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ request('from') }}">
        </div>
        <div class="col">
            <label for="to" class="form-label small mb-1">排定日期（迄）</label>
            <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ request('to') }}">
        </div>
        <div class="col">
            <button type="submit" class="btn btn-sm btn-primary">篩選</button>
            <a href="{{ route('maintenance-orders.index') }}" class="btn btn-sm btn-outline-secondary">清除</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>來源計畫</th>
                    <th>設備類別</th>
                    <th>來源</th>
                    <th>狀態</th>
                    <th>排定保養日期</th>
                    <th>建立時間</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php [$statusText, $statusColor] = $order->statusLabel(); @endphp
                    <tr>
                        <td>{{ $order->maintenancePlan?->name ?? '—' }}</td>
                        <td>{{ $order->device_category ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $order->sourceColor() }}">{{ $order->sourceLabel() }}</span></td>
                        <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                        <td>{{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">詳細</a>
                            @if (! $order->result)
                                <a href="{{ route('maintenance-orders.results.create', $order) }}" class="btn btn-sm btn-outline-success">回報結果</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            目前沒有保養工單，請到「保養計畫」頁面點選「建立工單」
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->links('pagination::bootstrap-5') }}
@endsection
