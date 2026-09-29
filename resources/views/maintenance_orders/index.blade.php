@extends('layouts.app')

@section('title', '保養工單')

@section('content')
    <h1 class="h3 mb-3">保養工單</h1>

    <table class="table table-bordered bg-white align-middle">
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
                    <td><span class="badge text-bg-secondary">{{ $order->sourceLabel() }}</span></td>
                    <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                    <td>{{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">詳細</a>
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

    {{ $orders->links('pagination::bootstrap-5') }}
@endsection
