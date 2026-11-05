@extends('layouts.app')

@section('title', '保養結果詳細')

@section('content')
    @php [$resultText, $resultColor] = $result->resultLabel(); @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">保養結果詳細</h1>
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
                    {{ $order->categoryName() ?? '—' }}
                    @if ($order->device)
                        <span class="text-muted">（設備：<a href="{{ route('device-profile.show', $order->device) }}">{{ $order->device->device_code }}</a>）</span>
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
                <dd class="col-sm-9">{{ $result->executor?->name ?? '—' }}</dd>

                <dt class="col-sm-3">執行時間</dt>
                <dd class="col-sm-9">{{ $result->executed_at?->format('Y-m-d H:i') ?? '—' }}</dd>

                <dt class="col-sm-3">說明 / 備註</dt>
                <dd class="col-sm-9">{{ $result->note ?? '—' }}</dd>

                @if ($result->isNg())
                    <dt class="col-sm-3">轉報修狀態</dt>
                    <dd class="col-sm-9">
                        @if ($result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_PENDING)
                            <span class="badge text-bg-warning">待轉報修（尚未建立報修單）</span>
                        @elseif ($result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_CONVERTED)
                            <span class="badge text-bg-success">已轉報修</span>
                            @if (\Illuminate\Support\Facades\Route::has('repairs.show'))
                                <a href="{{ route('repairs.show', $result->repair_request_id) }}">報修單 #{{ $result->repair_request_id }}</a>
                            @else
                                報修單 #{{ $result->repair_request_id }}
                            @endif
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

    {{-- 保養用料：一次用料跨兩個採購批次時會分成兩列，各自顯示那一批的單價（先進先出）。 --}}
    <div class="card mb-3">
        <div class="card-header">使用零件</div>
        @if ($result->partUsages->isEmpty())
            <div class="card-body text-muted small">這次保養沒有使用零件。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>零件編號</th><th>零件</th><th class="text-end">數量</th><th class="text-end">單價</th><th class="text-end">小計</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($result->partUsages as $usage)
                            <tr>
                                <td>{{ $usage->part?->part_code ?? '—' }}</td>
                                <td>{{ $usage->part?->name ?? '零件資料不存在' }}@if ($usage->part?->specification)（{{ $usage->part->specification }}）@endif</td>
                                <td class="text-end">{{ $usage->quantity }}</td>
                                <td class="text-end">{{ $usage->unit_price === null ? '缺少單價' : 'NT$ ' . number_format($usage->unit_price) }}</td>
                                <td class="text-end">{{ $usage->unit_price === null ? '—' : 'NT$ ' . number_format($usage->quantity * $usage->unit_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">附件</div>
        <div class="card-body text-muted small">
            共用附件機制尚未建立（規劃在本週之後）。等正式納入後，這裡會沿用該共用機制顯示這筆保養結果的附件，
            不會另外做一套附件上傳邏輯。
        </div>
    </div>
@endsection
