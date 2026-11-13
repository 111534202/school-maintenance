@extends('layouts.app')

@section('title', '保養計畫')

@section('content')
{{-- 風格 B：表格／清單自動切換，整頁捲動、表頭固定（data-no-fit）；有管理權限時點一列在對話框修改。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">保養計畫</h1>
        </div>
        @can('maintenance.manage')
            <a href="{{ route('maintenance-plans.create') }}" class="tb-btn tb-btn-primary" data-modal-frame data-modal-title="新增保養計畫" data-modal-size="lg">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>新增保養計畫
            </a>
        @endcan
    </div>

    @if ($plans->isEmpty())
        <div class="tb-card tb-empty">目前沒有保養計畫</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover" data-onion-primary="4">
            <thead>
                <tr>
                    <th>計畫名稱</th>
                    <th>設備類別</th>
                    <th>週期（天）</th>
                    <th>項目數</th>
                    <th>下次到期日</th>
                    <th>狀態</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr @can('maintenance.manage') data-modal-frame-href="{{ route('maintenance-plans.edit', $plan) }}" data-modal-title="修改保養計畫" data-modal-size="lg" tabindex="0" role="link" @endcan>
                        <td>{{ $plan->name }}</td>
                        <td>{{ $plan->categoryName() ?? '—' }}</td>
                        <td>{{ $plan->cycle_days }}</td>
                        <td>{{ $plan->maintenance_items_count }}</td>
                        <td>{{ $plan->next_due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if ($plan->is_active)
                                <span class="badge tb-pill tb-pill-ok">啟用中</span>
                            @else
                                <span class="badge tb-pill tb-pill-muted">已停用</span>
                            @endif
                        </td>
                        <td class="text-end tb-col-fit">
                            @can('maintenance.manage')
                                <a href="{{ route('maintenance-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="修改保養計畫" data-modal-size="lg">修改</a>
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
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $plans])</div>
    @endif
</div>
@endsection
