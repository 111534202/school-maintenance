
@extends('layouts.app')

@section('title', '零件列表')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0">零件列表</h3>
        <a href="{{ route('parts.create') }}" class="btn btn-primary">
            新增零件
        </a>
    </div>

    <div class="card p-3 mb-3">
        <form method="GET" action="{{ route('parts.index') }}" class="d-flex flex-wrap gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                style="max-width: 360px;"
                value="{{ request('search') }}"
                placeholder="搜尋零件名稱"
            >
            <button type="submit" class="btn btn-outline-primary">搜尋</button>
            <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary">清除</a>
        </form>
    </div>

    @if ($parts->isEmpty())
        <div class="alert alert-info">
            目前沒有零件資料。
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>編號</th>
                            <th>零件名稱</th>
                            <th>規格</th>
                            <th>單價</th>
                            <th>目前庫存</th>
                            <th>安全庫存</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parts as $part)
                            <tr>
                                <td>{{ $part->id }}</td>
                                <td>{{ $part->name }}</td>
                                <td>{{ $part->specification ?? '—' }}</td>
                                <td>{{ $part->unit_price }}</td>
                                <td>
                                    {{ $part->current_stock }}

                                    @if ($part->current_stock < $part->safety_stock)
                                        <span class="badge text-bg-danger">⚠ 低庫存</span>
                                    @endif
                                </td>
                                <td>{{ $part->safety_stock }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a
                                            href="{{ route('parts.edit', $part) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >修改</a>

                                        <form
                                            method="POST"
                                            action="{{ route('parts.destroy', $part) }}"
                                            onsubmit="return confirm('確定要刪除這個零件嗎？');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                刪除
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
