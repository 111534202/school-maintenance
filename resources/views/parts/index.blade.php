@extends('layouts.app')

@section('title', '零件庫存列表')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">零件庫存列表</h1>
        <a href="{{ route('parts.create') }}" class="btn btn-primary">新增零件</a>
    </div>

    <form method="GET" action="{{ route('parts.index') }}" class="row row-cols-1 row-cols-sm-2 row-cols-md-auto g-2 align-items-end bg-white p-3 rounded shadow-sm mb-3">
        <div class="col">
            <label for="keyword" class="form-label small mb-1">搜尋（零件編號／名稱／規格／品牌／型號）</label>
            <input type="text" name="keyword" id="keyword" class="form-control form-control-sm" style="min-width: 280px;"
                value="{{ request('keyword') }}" placeholder="例如 PART-1 或 HDMI">
        </div>
        <div class="col">
            <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
            <a href="{{ route('parts.index') }}" class="btn btn-sm btn-outline-secondary">清除</a>
        </div>
    </form>

    @if ($parts->isEmpty())
        <div class="alert alert-info">
            {{ request()->filled('keyword') ? '找不到符合的零件。' : '目前沒有零件資料。' }}
        </div>
    @else
        <div class="table-responsive">
            {{-- 平均單價：目前庫存各採購批次的加權平均（用料成本採先進先出，詳見零件履歷）。 --}}
            <table class="table table-bordered table-hover bg-white align-middle" data-onion-primary="1,2">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">零件編號</th>
                        <th>名稱</th>
                        <th>規格</th>
                        <th>品牌／型號</th>
                        <th class="text-end text-nowrap">平均單價</th>
                        <th class="text-end text-nowrap">目前庫存</th>
                        <th class="text-end text-nowrap">安全庫存</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parts as $part)
                        @php($average = $part->averageUnitPrice())
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('parts.show', $part) }}">{{ $part->part_code }}</a></td>
                            <td><a href="{{ route('parts.show', $part) }}" class="text-decoration-none">{{ $part->name }}</a></td>
                            <td>{{ $part->specification ?? '—' }}</td>
                            <td>{{ $part->brandModel() ?? '—' }}</td>
                            <td class="text-end text-nowrap">{{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}</td>
                            <td class="text-end text-nowrap">
                                {{ $part->current_stock }}
                                @if ($part->isLowStock())
                                    <span class="badge text-bg-danger">⚠ 低庫存</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $part->safety_stock }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('parts.show', $part) }}" class="btn btn-sm btn-outline-primary">查看履歷</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $parts->links() }}
    @endif
@endsection
