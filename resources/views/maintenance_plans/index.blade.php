@extends('layouts.app')

@section('title', '保養計畫')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">保養計畫</h1>
        @can('maintenance.manage')
            <a href="{{ route('maintenance-plans.create') }}" class="btn btn-primary">新增保養計畫</a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>計畫名稱</th>
                    <th>設備類別</th>
                    <th>週期（天）</th>
                    <th>項目數</th>
                    <th>下次到期日</th>
                    <th>狀態</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td>{{ $plan->device_category ?? '—' }}</td>
                        <td>{{ $plan->cycle_days }}</td>
                        <td>{{ $plan->maintenance_items_count }}</td>
                        <td>{{ $plan->next_due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if ($plan->is_active)
                                <span class="badge text-bg-success">啟用中</span>
                            @else
                                <span class="badge text-bg-secondary">已停用</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('maintenance.manage')
                                <a href="{{ route('maintenance-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">修改</a>
                                <form action="{{ route('maintenance-plans.toggle-status', $plan) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ $plan->is_active ? '停用' : '重新啟用' }}
                                    </button>
                                </form>
                                @if ($plan->is_active)
                                    <form action="{{ route('maintenance-plans.create-order', $plan) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">建立工單</button>
                                    </form>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">目前沒有保養計畫</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $plans->links('pagination::bootstrap-5') }}
@endsection
