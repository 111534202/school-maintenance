@extends('layouts.app')

@section('title', '設備類別管理')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備類別管理</h3>
        <a href="{{ route('device-categories.create') }}" class="btn btn-primary">新增類別</a>
    </div>

    <form method="GET" action="{{ route('device-categories.index') }}" class="row g-2 mb-3">
        <div class="col-8 col-md-4">
            <input type="text" name="keyword" class="form-control form-control-sm" placeholder="搜尋類別名稱" value="{{ request('keyword') }}">
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">搜尋</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>類別名稱</th>
                        <th>使用中設備數</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->devices_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('device-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                <form method="POST" action="{{ route('device-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('確定要刪除此類別嗎？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">尚無設備類別</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $categories->links() }}</div>
@endsection
