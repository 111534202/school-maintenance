@extends('layouts.app')

@section('title', '保養工單詳細')

@section('content')
    @php [$statusText, $statusColor] = $maintenanceOrder->statusLabel(); @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">保養工單詳細</h1>
        <a href="{{ route('maintenance-orders.index') }}" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">工單編號</dt>
                <dd class="col-sm-9">#{{ $maintenanceOrder->id }}</dd>

                <dt class="col-sm-3">來源保養計畫</dt>
                <dd class="col-sm-9">
                    @if ($maintenanceOrder->maintenancePlan)
                        {{ $maintenanceOrder->maintenancePlan->name }}
                        <span class="text-muted">（週期 {{ $maintenanceOrder->maintenancePlan->cycle_days }} 天）</span>
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">設備 / 類別</dt>
                <dd class="col-sm-9">
                    {{ $maintenanceOrder->device_category ?? '—' }}
                    @if ($maintenanceOrder->device_id)
                        <span class="text-muted">（設備編號：{{ $maintenanceOrder->device_id }}）</span>
                    @endif
                </dd>

                <dt class="col-sm-3">來源</dt>
                <dd class="col-sm-9"><span class="badge text-bg-secondary">{{ $maintenanceOrder->sourceLabel() }}</span></dd>

                <dt class="col-sm-3">狀態</dt>
                <dd class="col-sm-9"><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></dd>

                <dt class="col-sm-3">排定保養日期</dt>
                <dd class="col-sm-9">{{ $maintenanceOrder->scheduled_date?->format('Y-m-d') ?? '—' }}</dd>

                <dt class="col-sm-3">建立時間</dt>
                <dd class="col-sm-9">{{ $maintenanceOrder->created_at?->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
    </div>

    @if ($maintenanceOrder->maintenancePlan?->maintenanceItems->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">來源計畫包含的保養項目</div>
            <ul class="list-group list-group-flush">
                @foreach ($maintenanceOrder->maintenancePlan->maintenanceItems as $item)
                    <li class="list-group-item">
                        {{ $item->name }}
                        @if ($item->category)
                            <span class="text-muted small">（{{ $item->category }}）</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
