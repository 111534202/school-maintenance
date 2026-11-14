@extends('layouts.app')

@section('title', '保養紀錄')

@section('content')
    @php [$statusText, $statusColor] = $maintenanceOrder->statusLabel(); @endphp

    {{-- 在對話框裡打開時整列不顯示：標題跟對話框重複；「回報保養結果」有 data-modal-header，會被搬到對話框標題列（✕ 左邊）。 --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" data-modal-hide>
        <h1 class="tb-title mb-0">保養紀錄</h1>
        <div>
            @if (! $maintenanceOrder->result)
                @can('maintenance.report')
                    <a href="{{ route('maintenance-orders.results.create', $maintenanceOrder) }}" class="btn btn-outline-success btn-sm" data-modal-header
                        data-modal-frame data-modal-title="回報保養結果" data-modal-size="lg">回報保養結果</a>
                @endcan
            @endif
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
                <dd class="col-sm-9">{{ $maintenanceOrder->scheduled_date?->format('Y-m-d') ?? '—' }}</dd>

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
