@extends('layouts.app')

@section('title', '保養紀錄')

@section('content')
    @php [$statusText, $statusColor] = $maintenanceOrder->statusLabel(); @endphp

    {{-- 在對話框裡打開時只有標題隱藏（跟對話框標題重複）；「回報保養結果」有 data-modal-header，會被搬到對話框標題列（✕ 左邊）；
         修改日期、取消工單留在頁面上。 --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="tb-title mb-0" data-modal-hide>保養紀錄</h1>
        <div class="d-inline-flex flex-wrap gap-2">
            @if (! $maintenanceOrder->result && $maintenanceOrder->canBeReportedBy(auth()->user()))
                <a href="{{ route('maintenance-orders.results.create', $maintenanceOrder) }}" class="btn btn-outline-success btn-sm" data-modal-header
                    data-modal-frame data-modal-title="回報保養結果" data-modal-size="lg">回報保養結果</a>
            @endif
            {{-- 修改（排定日期）與取消（刪除）：需要 maintenance.manage；已完成或已回報結果的工單不能改、不能取消。
                 取消與刪除是同一件事（軟刪除，資料與操作紀錄保留），送出前先跳出確認視窗。 --}}
            @can('maintenance.manage')
                @if ($maintenanceOrder->status !== \App\Models\MaintenanceOrder::STATUS_COMPLETED)
                    <a href="{{ route('maintenance-orders.edit', $maintenanceOrder) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>修改日期</a>
                @endif
                @if ($maintenanceOrder->status !== \App\Models\MaintenanceOrder::STATUS_COMPLETED && ! $maintenanceOrder->result)
                    <form method="POST" action="{{ route('maintenance-orders.destroy', $maintenanceOrder) }}"
                        data-confirm="請問確定要取消保養工單 #{{ $maintenanceOrder->id }} 嗎？取消後會從列表與統計中移除，操作紀錄仍會保留。" data-confirm-ok="確認取消">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle me-1"></i>取消工單</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">工單編號</dt>
                <dd class="col-sm-9">#{{ $maintenanceOrder->id }}</dd>

                <dt class="col-sm-3">來源保養計畫</dt>
                <dd class="col-sm-9">
                    @if ($maintenanceOrder->maintenancePlan)
                        @include('maintenance_orders._plan_name', ['plan' => $maintenanceOrder->maintenancePlan, 'withCycle' => true])
                    @elseif ($maintenanceOrder->source === 'ai')
                        AI 預防保養（無來源計畫，由 AI 風險候選核准建立）
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">設備 / 類別</dt>
                <dd class="col-sm-9">@include('maintenance_orders._device_category', ['order' => $maintenanceOrder])</dd>

                <dt class="col-sm-3">來源</dt>
                <dd class="col-sm-9"><span class="badge text-bg-{{ $maintenanceOrder->sourceColor() }}">{{ $maintenanceOrder->sourceLabel() }}</span></dd>

                <dt class="col-sm-3">狀態</dt>
                <dd class="col-sm-9"><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></dd>

                <dt class="col-sm-3">排定保養日期</dt>
                <dd class="col-sm-9">
                    {{ $maintenanceOrder->scheduled_date?->format('Y-m-d') ?? '—' }}
                    @if ($maintenanceOrder->isOverdue())
                        <span class="badge text-bg-danger ms-1">逾期 {{ $maintenanceOrder->scheduled_date->diffInDays(now()->startOfDay(), true) }} 天</span>
                    @endif
                </dd>

                <dt class="col-sm-3">負責人</dt>
                <dd class="col-sm-9">
                    {{-- 保養管理者可以指派負責人（只能指派給有保養回報權限的人）；指派之後只有負責人與保養管理者能回報，通知鈴鐺也只提醒負責人。留空＝沒指派。 --}}
                    @if ($maintenanceOrder->status !== \App\Models\MaintenanceOrder::STATUS_COMPLETED && auth()->user()->can('maintenance.manage'))
                        <form method="POST" action="{{ route('maintenance-orders.assign', $maintenanceOrder) }}" class="d-flex flex-wrap gap-2 align-items-center">
                            @csrf
                            @method('PATCH')
                            <select name="assigned_to" class="form-select form-select-sm w-auto" aria-label="負責人">
                                <option value="">（還沒指派）</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected($maintenanceOrder->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-person-check me-1"></i>指派</button>
                        </form>
                    @else
                        {{ $maintenanceOrder->assignee?->name ?? '—' }}
                    @endif
                </dd>

                <dt class="col-sm-3">建立時間</dt>
                <dd class="col-sm-9">{{ $maintenanceOrder->created_at?->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
    </div>

    @if ($maintenanceOrder->result)
        @php [$resultText, $resultColor] = $maintenanceOrder->result->resultLabel(); @endphp
        <div class="card mt-3">
            <div class="card-header bg-white form-section-title d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard-check me-2"></i>保養結果</span>
                <a href="{{ route('maintenance-orders.results.show', $maintenanceOrder) }}" class="btn btn-sm tb-chip-btn" data-modal-frame data-modal-title="保養結果詳細" data-modal-size="xl">查看完整紀錄</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">結果</dt>
                    <dd class="col-sm-9"><span class="badge text-bg-{{ $resultColor }}">{{ $resultText }}</span></dd>

                    <dt class="col-sm-3">執行人</dt>
                    <dd class="col-sm-9">{{ $maintenanceOrder->result->executor?->name ?? '—' }}</dd>

                    <dt class="col-sm-3">執行時間</dt>
                    <dd class="col-sm-9">{{ $maintenanceOrder->result->executed_at?->format('Y-m-d H:i') }}</dd>

                    <dt class="col-sm-3">備註</dt>
                    <dd class="col-sm-9">{{ $maintenanceOrder->result->note ?? '—' }}</dd>

                    @if ($maintenanceOrder->result->isNg())
                        <dt class="col-sm-3">轉報修狀態</dt>
                        <dd class="col-sm-9">
                            @if ($maintenanceOrder->result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_PENDING)
                                <span class="badge text-bg-warning">待轉報修（尚未建立報修單）</span>
                            @elseif ($maintenanceOrder->result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_CONVERTED)
                                <span class="badge text-bg-success">已轉報修</span>
                                @if (\Illuminate\Support\Facades\Route::has('repairs.show'))
                                    <a href="{{ route('repairs.show', $maintenanceOrder->result->repair_request_id) }}">報修單 #{{ $maintenanceOrder->result->repair_request_id }}</a>
                                @else
                                    報修單 #{{ $maintenanceOrder->result->repair_request_id }}
                                @endif
                            @else
                                {{ $maintenanceOrder->result->ng_conversion_status ?? '—' }}
                            @endif
                        </dd>
                    @endif
                </dl>
            </div>
        </div>
    @endif

    @if ($maintenanceOrder->maintenancePlan?->maintenanceItems->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header bg-white form-section-title"><i class="bi bi-list-check me-2"></i>來源計畫包含的保養項目</div>
            <ul class="list-group list-group-flush">
                @foreach ($maintenanceOrder->maintenancePlan->maintenanceItems as $item)
                    <li class="list-group-item">
                        {{ $item->name }}
                        @if ($item->deviceCategory)
                            <span class="text-muted small">（{{ $item->deviceCategory->name }}）</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
