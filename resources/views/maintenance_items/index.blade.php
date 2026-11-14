@extends('layouts.app')

@section('title', '保養項目')

@section('content')
{{-- 風格 B：表格／清單自動切換，整頁捲動、表頭固定（data-no-fit）；有管理權限時點一列在對話框修改。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">保養項目</h1>
        </div>
        @can('maintenance.manage')
            <a href="{{ route('maintenance-items.create') }}" class="tb-btn tb-btn-primary" data-modal-frame data-modal-title="新增保養項目">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>新增保養項目
            </a>
        @endcan
    </div>

    @include('layouts.partials.keyword-search', ['action' => route('maintenance-items.index'), 'placeholder' => '項目名稱、說明、設備類別'])

    @if ($items->isEmpty())
        <div class="tb-card tb-empty">{{ request('keyword') ? '找不到符合搜尋條件的保養項目' : '目前沒有保養項目' }}</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover">
            <thead>
                <tr>
                    <th>名稱</th>
                    <th>分類</th>
                    <th>預設週期（天）</th>
                    <th>狀態</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr @can('maintenance.manage') data-modal-frame-href="{{ route('maintenance-items.edit', $item) }}" data-modal-title="修改保養項目" tabindex="0" role="link" @endcan>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->deviceCategory?->name ?? '—' }}</td>
                        <td>{{ $item->default_cycle_days ?? '—' }}</td>
                        <td>
                            @if ($item->is_active)
                                <span class="badge tb-pill tb-pill-ok">啟用中</span>
                            @else
                                <span class="badge tb-pill tb-pill-muted">已停用</span>
                            @endif
                        </td>
                        <td class="text-end tb-col-fit">
                            @can('maintenance.manage')
                                {{-- 操作改成圓形圖示按鈕（跟用戶、部門等主檔一樣）：鉛筆＝修改、開關＝停用／重新啟用。 --}}
                                <div class="d-inline-flex gap-1">
                                <a href="{{ route('maintenance-items.edit', $item) }}" class="btn btn-sm btn-outline-primary icon-btn" data-modal-frame data-modal-title="修改保養項目"
                                    title="修改" aria-label="修改"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('maintenance-items.toggle-status', $item) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-warning icon-btn"
                                        title="{{ $item->is_active ? '停用' : '重新啟用' }}" aria-label="{{ $item->is_active ? '停用' : '重新啟用' }}">
                                        <i class="bi {{ $item->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                </form>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $items])</div>
    @endif
</div>
@endsection
