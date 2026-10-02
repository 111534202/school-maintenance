@extends('layouts.app')

@section('title', '保養結果詳細')

@section('content')
    @php [$resultText, $resultColor] = $result->resultLabel(); @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">保養結果詳細</h1>
        <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">返回工單</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">來源工單</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">工單編號</dt>
                <dd class="col-sm-9">#{{ $order->id }}</dd>

                <dt class="col-sm-3">來源保養計畫</dt>
                <dd class="col-sm-9">{{ $order->maintenancePlan?->name ?? '—' }}</dd>

                <dt class="col-sm-3">設備 / 類別</dt>
                <dd class="col-sm-9">
                    {{ $order->device_category ?? '—' }}
                    @if ($order->device_id)
                        <span class="text-muted">（設備編號：{{ $order->device_id }}）</span>
                    @endif
                </dd>

                <dt class="col-sm-3">排定保養日期</dt>
                <dd class="col-sm-9">{{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">保養結果</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">結果</dt>
                <dd class="col-sm-9"><span class="badge text-bg-{{ $resultColor }}">{{ $resultText }}</span></dd>

                <dt class="col-sm-3">執行人</dt>
                <dd class="col-sm-9">{{ $result->executed_by ?? '—' }}</dd>

                <dt class="col-sm-3">執行時間</dt>
                <dd class="col-sm-9">{{ $result->executed_at?->format('Y-m-d H:i') ?? '—' }}</dd>

                <dt class="col-sm-3">說明 / 備註</dt>
                <dd class="col-sm-9">{{ $result->notes ?? '—' }}</dd>

                @if ($result->isNg())
                    <dt class="col-sm-3">轉報修狀態</dt>
                    <dd class="col-sm-9">
                        @if ($result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_PENDING)
                            <span class="badge text-bg-warning">待轉報修（等彭仕衡介面串接）</span>
                        @else
                            {{ $result->ng_conversion_status ?? '—' }}
                        @endif
                    </dd>
                @endif

                <dt class="col-sm-3">回報時間</dt>
                <dd class="col-sm-9">{{ $result->created_at?->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">附件</div>
        <div class="card-body text-muted small">
            共用附件機制尚未建立（規劃在本週之後）。等正式納入後，這裡會沿用該共用機制顯示這筆保養結果的附件，
            不會另外做一套附件上傳邏輯。
        </div>
    </div>
@endsection
