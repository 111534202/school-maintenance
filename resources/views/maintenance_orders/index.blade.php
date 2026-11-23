@extends('layouts.app')

@section('title', '保養工單')

@section('content')
{{-- 風格 B：篩選放在卡片裡（放得下排一列、不夠寬換行）；表格／清單自動切換，整頁捲動、表頭固定；點一列在對話框打開工單詳細。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">保養工單</h1>
        </div>
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
                {{-- 保養結果 NG：轉出的報修單還沒全部結案＝已轉報修；全部結案後＝已完成。 --}}
                <option value="converted" @selected(request('status') === 'converted')>已轉報修</option>
                <option value="completed" @selected(request('status') === 'completed')>已完成</option>
                <option value="aborted" @selected(request('status') === 'aborted')>已中止</option>
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
        {{-- 從通知鈴鐺連過來的「只看我負責的」「只看還沒完成的」「還沒指派負責人」，按「篩選」時要一併帶著，不然一篩選就被洗掉。
             （負責人不另外放下拉選單：要找某位負責人的工單，在關鍵字輸入姓名。） --}}
        @foreach (['mine', 'unfinished'] as $carry)
            @if (request()->boolean($carry))
                <input type="hidden" name="{{ $carry }}" value="1">
            @endif
        @endforeach
        @if (request()->filled('assignee'))
            <input type="hidden" name="assignee" value="{{ request('assignee') }}">
        @endif
        @include('layouts.partials.filter-actions', ['reset' => route('maintenance-orders.index'), 'fields' => ['keyword', 'status', 'due', 'assignee', 'mine', 'unfinished', 'source', 'from', 'to']])
    </form>
    </div>

    {{-- 目前套用的進階篩選說明（一行一項，附清除連結）。 --}}
    @foreach (['mine' => '只顯示指派給我的保養工單', 'unfinished' => '只顯示還沒完成（待處理、進行中）的保養工單', 'assignee' => '只顯示還沒指派負責人的保養工單'] as $param => $text)
        @if ($param === 'assignee' ? request('assignee') === 'none' : request()->boolean($param))
            <div class="text-muted small d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <span><i class="bi bi-funnel me-2"></i>{{ $text }}</span>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('maintenance-orders.index', request()->except([$param, 'page'])) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
            </div>
        @endif
    @endforeach

    @if ($orders->isEmpty())
        <div class="tb-card tb-empty">目前沒有保養工單。保養計畫的下次到期日前 3 天，系統會自動產生工單</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover">
            <thead>
                <tr>
                    <th>工單編號</th>
                    {{-- 手機清單：標題是工單編號，副標題「來源計畫 · 排定 日期」。 --}}
                    <th data-onion-sub="{v}">來源計畫</th>
                    <th>狀態</th>
                    <th>負責人</th>
                    <th data-onion-sub="排定 {v}">排定保養日期</th>
                    <th>建立時間</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php [$statusText, $statusColor] = $order->statusLabel(); @endphp
                    <tr data-modal-frame-href="{{ route('maintenance-orders.show', $order) }}" data-modal-title="保養紀錄" data-modal-size="xl" tabindex="0" role="link">
                        {{-- 工單編號：AI 預防保養是 AI-開頭（淺藍）、定期保養是 MO-開頭（灰），列表上一眼就分得出來來源。
                             保養的設備／類別（一張工單可以有好幾個）在工單詳細裡看；點這一列在對話框打開。 --}}
                        <td class="fw-semibold"><span class="badge text-bg-{{ $order->sourceColor() }}" data-onion-text title="{{ $order->sourceLabel() }}">{{ $order->displayNumber() }}</span></td>
                        <td>{{ $order->maintenancePlan?->name ?? ($order->source === 'ai' ? 'AI 預防保養（無計畫）' : '—') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span>
                        </td>
                        <td>{{ $order->assignee?->name ?? '—' }}</td>
                        <td>
                            {{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}
                            {{-- 還沒做完、排定日已經過了：標紅色「逾期」。 --}}
                            @if ($order->isOverdue())<span class="badge text-bg-danger ms-1">逾期 {{ $order->scheduled_date->diffInDays(now()->startOfDay(), true) }} 天</span>@endif
                        </td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end tb-col-fit">
                            {{-- 工單詳細：點這一列在對話框打開（不另外放「詳細」按鈕）。
                                 待處理：「確認派工」打開工單詳細（在那裡選負責人）；進行中：負責人與保養管理者看到「回報結果」；
                                 已經回報：綠底、不能按的「已回報」。三個按鈕一樣寬（四個字）對齊。 --}}
                            @if ($order->result)
                                <span class="btn btn-sm btn-success mo-action disabled" aria-disabled="true">已回報</span>
                            @elseif ($order->canBeDispatchedBy(auth()->user()))
                                <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-sm btn-outline-primary mo-action" data-modal-frame data-modal-title="保養紀錄" data-modal-size="xl">確認派工</a>
                            @elseif ($order->canBeReportedBy(auth()->user()))
                                <a href="{{ route('maintenance-orders.results.create', $order) }}" class="btn btn-sm btn-outline-success mo-action" data-modal-frame data-modal-title="回報保養結果" data-modal-size="lg">回報結果</a>
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

    {{-- 右下角：匯出 Excel（先開預覽視窗，勾選目前搜尋與篩選條件下要匯出的欄位與工單；含保養結果）。跟設備、報修等列表一樣。 --}}
    <div class="tb-bottom-actions">
        <button type="button" class="tb-btn tb-btn-outline" data-bs-toggle="modal" data-bs-target="#maintenanceOrderExportModal"><i class="bi bi-download" aria-hidden="true"></i><span class="tb-label-long">匯出 Excel</span><span class="tb-label-short">匯出</span></button>
    </div>
</div>

@include('layouts.partials.export-preview', [
    'modalId' => 'maintenanceOrderExportModal', 'title' => '匯出保養工單', 'action' => route('maintenance-orders.export'),
    'columnsClass' => \App\Support\MaintenanceOrderExportColumns::class, 'items' => $exportOrders, 'total' => $orders->total(), 'unit' => '張工單',
])
<style>
    /* 「回報結果」與「已回報」一樣寬（四個字），上下對齊；已回報是綠底實心、不能按。 */
    .mo-action { min-width: calc(4em + 1.5rem); text-align: center; }
    .mo-action.disabled { opacity: 1; }
</style>
@endsection
