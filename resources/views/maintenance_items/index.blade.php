@extends('layouts.app')

@section('title', '保養項目')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">保養項目</h1>
        @can('maintenance.manage')
            <a href="{{ route('maintenance-items.create') }}" class="btn btn-primary">新增保養項目</a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>名稱</th>
                    <th>分類</th>
                    <th>預設週期（天）</th>
                    <th>狀態</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category ?? '—' }}</td>
                        <td>{{ $item->default_cycle_days ?? '—' }}</td>
                        <td>
                            @if ($item->is_active)
                                <span class="badge text-bg-success">啟用中</span>
                            @else
                                <span class="badge text-bg-secondary">已停用</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('maintenance.manage')
                                <a href="{{ route('maintenance-items.edit', $item) }}" class="btn btn-sm btn-outline-primary">修改</a>
                                <form action="{{ route('maintenance-items.toggle-status', $item) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ $item->is_active ? '停用' : '重新啟用' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">目前沒有保養項目</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links('pagination::bootstrap-5') }}
@endsection
