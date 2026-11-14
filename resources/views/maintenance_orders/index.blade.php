@extends('layouts.app')

@section('title', '保養工單')

@section('content')
{{-- 風格 B：篩選放在卡片裡（放得下排一列、不夠寬換行）；表格／清單自動切換，整頁捲動、表頭固定；點一列在對話框打開工單詳細。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">保養工單</h1>
        </div>
        {{-- 匯出 Excel：匯出目前搜尋與篩選條件下的全部保養工單（不分頁，含保養結果）。 --}}
        <a class="tb-btn tb-btn-outline" href="{{ route('maintenance-orders.export', request()->query()) }}"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i>匯出 Excel</a>
    </div>

    <div class="tb-card tb-card-pad">
    <form method="GET" class="filter-bar tb-form-row tb-filter" data-no-onion>
        <div>
            <label for="keyword" class="form-label small mb-1">關鍵字</label>
            <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 14rem;" value="{{ request('keyword') }}" placeholder="設備、計畫、類別、負責人、編號">
        </div>
        <div>
            <label for="status" class="form-label small mb-1">狀態</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="pending" @selected(request('status') === 'pending')>待處理</option>
                <option value="in_progress" @selected(request('status') === 'in_progress')>進行中</option>
                <option value="completed" @selected(request('status') === 'completed')>已完成</option>
            </select>
        </div>
        <div>
            <label for="due" class="form-label small mb-1">期限</label>
            <select name="due" id="due" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="overdue" @selected(request('due') === 'overdue')>已逾期</option>
                <option value="week" @selected(request('due') === 'week')>7 天內到期（含逾期）</option>
            </select>
        </div>
        <div>
            <label for="assignee" class="form-label small mb-1">負責人</label>
            <select name="assignee" id="assignee" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="none" @selected(request('assignee') === 'none')>還沒指派</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected((string) request('assignee') === (string) $assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="source" class="form-label small mb-1">來源</label>
            <select name="source" id="source" class="form-select form-select-sm">
                <option value="">全部</option>
                <option value="periodic" @selected(request('source') === 'periodic')>定期</option>
                <option value="ai" @selected(request('source') === 'ai')>AI 辨識</option>
            </select>
        </div>
        <div>
            <label for="from" class="form-label small mb-1">排定日期（起）</label>
            <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ request('from') }}">
        </div>
        <div>
            <label for="to" class="form-label small mb-1">排定日期（迄）</label>
            <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ request('to') }}">
        </div>
        @include('layouts.partials.filter-actions', ['reset' => route('maintenance-orders.index'), 'fields' => ['keyword', 'status', 'due', 'assignee', 'mine', 'source', 'from', 'to']])
    </form>
    </div>

    @if ($orders->isEmpty())
        <div class="tb-card tb-empty">目前沒有保養工單，請到「保養計畫」頁面點選「建立工單」</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover" data-onion-primary="5">
            <thead>
                <tr>
                    <th>設備</th>
                    <th>來源計畫</th>
                    <th>設備類別</th>
                    <th>來源</th>
                    <th>狀態</th>
                    <th>負責人</th>
                    <th>排定保養日期</th>
                    <th>建立時間</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php [$statusText, $statusColor] = $order->statusLabel(); @endphp
                    <tr data-modal-frame-href="{{ route('maintenance-orders.show', $order) }}" data-modal-title="保養紀錄" data-modal-size="xl" tabindex="0" role="link">
                        {{-- 設備編號：點字換頁到設備履歷；點這一列的其他地方在對話框打開工單詳細。 --}}
                        <td class="fw-semibold">
                            @if ($order->device)
                                <a href="{{ route('device-profile.show', $order->device) }}">{{ $order->device->device_code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $order->maintenancePlan?->name ?? ($order->source === 'ai' ? 'AI 預防保養（無計畫）' : '—') }}</td>
                        <td>{{ $order->categoryName() ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $order->sourceColor() }}">{{ $order->sourceLabel() }}</span></td>
                        <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                        <td>{{ $order->assignee?->name ?? '—' }}</td>
                        <td>
                            {{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}
                            {{-- 還沒做完、排定日已經過了：標紅色「逾期」。 --}}
                            @if ($order->isOverdue())<span class="badge text-bg-danger ms-1">逾期 {{ $order->scheduled_date->diffInDays(now()->startOfDay(), true) }} 天</span>@endif
                        </td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end tb-col-fit">
                            {{-- 工單詳細：點這一列在對話框打開（不另外放「詳細」按鈕）。
                                 還沒回報：「回報結果」在對話框填寫；已經回報：綠底、不能按的「已回報」，跟「回報結果」一樣寬（四個字）對齊。 --}}
                            @if ($order->result)
                                <span class="btn btn-sm btn-success mo-action disabled" aria-disabled="true">已回報</span>
                            @else
                                @if (auth()->user()->can('maintenance.report') && $order->canBeReportedBy(auth()->user()))
                                    <a href="{{ route('maintenance-orders.results.create', $order) }}" class="btn btn-sm btn-outline-success mo-action" data-modal-frame data-modal-title="回報保養結果" data-modal-size="lg">回報結果</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $orders])</div>
    @endif
</div>
<style>
    /* 「回報結果」與「已回報」一樣寬（四個字），上下對齊；已回報是綠底實心、不能按。 */
    .mo-action { min-width: calc(4em + 1.5rem); text-align: center; }
    .mo-action.disabled { opacity: 1; }
</style>
@endsection
