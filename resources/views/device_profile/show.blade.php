@extends('layouts.app')

@section('title', '設備履歷：'.$device->device_code)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">設備履歷：{{ $device->device_code }}</h1>
        <a href="{{ route('device-profile.index') }}" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">基本資料</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">設備編號</dt>
                <dd class="col-sm-9">{{ $device->device_code }}</dd>

                <dt class="col-sm-3">資產編號</dt>
                <dd class="col-sm-9">{{ $device->asset_code ?? '—' }}</dd>

                <dt class="col-sm-3">類別</dt>
                <dd class="col-sm-9">{{ $device->category?->name ?? '—' }}</dd>

                <dt class="col-sm-3">品牌 / 型號</dt>
                <dd class="col-sm-9">{{ $device->brand }} {{ $device->model }}</dd>

                <dt class="col-sm-3">序號</dt>
                <dd class="col-sm-9">{{ $device->serial_number ?? '—' }}</dd>

                <dt class="col-sm-3">保固期限</dt>
                <dd class="col-sm-9">{{ $device->warranty_until?->format('Y-m-d') ?? '—' }}</dd>

                <dt class="col-sm-3">所在教室</dt>
                <dd class="col-sm-9">
                    @if ($device->classroom)
                        {{ $device->classroom->room_code }} - {{ $device->classroom->room_name }}
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">狀態</dt>
                <dd class="col-sm-9"><span class="badge text-bg-secondary">{{ $device->status }}</span></dd>

                <dt class="col-sm-3">核心設備</dt>
                <dd class="col-sm-9">{{ $device->is_core ? '是' : '否' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>保養紀錄</span>
            <span class="text-muted small">
                共 {{ $maintenanceStats['total'] }} 筆、已完成 {{ $maintenanceStats['completed'] }} 筆
                （OK {{ $maintenanceStats['ok'] }} / NG {{ $maintenanceStats['ng'] }}）
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>來源</th>
                        <th>來源計畫</th>
                        <th>狀態</th>
                        <th>排定日期</th>
                        <th>結果</th>
                        <th>執行人</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maintenanceOrders as $order)
                        @php [$statusText, $statusColor] = $order->statusLabel(); @endphp
                        <tr>
                            <td><span class="badge text-bg-{{ $order->sourceColor() }}">{{ $order->sourceLabel() }}</span></td>
                            <td>{{ $order->maintenancePlan?->name ?? ($order->source === 'ai' ? 'AI 預防保養（無計畫）' : '—') }}</td>
                            <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                            <td>{{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                @if ($order->result)
                                    @php [$resultText, $resultColor] = $order->result->resultLabel(); @endphp
                                    <span class="badge text-bg-{{ $resultColor }}">{{ $resultText }}</span>
                                @else
                                    <span class="text-muted">尚未回報</span>
                                @endif
                            </td>
                            <td>{{ $order->result->executed_by ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">這台設備目前沒有保養紀錄</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>AI 風險評估（規則式，非機器學習）</span>
            <span class="text-muted small">{{ $prediction['algorithm'] ?? '' }}</span>
        </div>
        <div class="card-body">
            @if ($prediction['risk_score'] === null)
                <p class="mb-0 text-muted">{{ $prediction['recommended_action'] }}</p>
            @else
                @php $overThreshold = $prediction['risk_score'] >= $prediction['threshold']; @endphp
                <p class="mb-2">
                    風險分數 <strong class="fs-5">{{ number_format($prediction['risk_score'], 3) }}</strong>
                    <span class="text-muted">／ 門檻 {{ number_format($prediction['threshold'], 2) }}</span>
                    <span class="badge text-bg-{{ $overThreshold ? 'danger' : 'success' }} ms-2">{{ $overThreshold ? '達門檻' : '未達門檻' }}</span>
                </p>
                @if ($prediction['recommended_action'])
                    <p class="mb-2">{{ $prediction['recommended_action'] }}</p>
                @endif
                <div class="table-responsive">
                    @include('preventive_candidates._explanation', ['explanation' => $prediction['explanation']])
                </div>
            @endif
            @foreach ($prediction['notes'] ?? [] as $note)
                <div class="text-muted small">※ {{ $note }}</div>
            @endforeach
        </div>
        @if ($candidates->isNotEmpty())
            <div class="table-responsive border-top">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>候選提出時間</th><th class="text-end">風險分數</th><th>狀態</th><th>對應工單</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($candidates as $candidate)
                            @php [$cText, $cColor] = $candidate->statusLabel(); @endphp
                            <tr>
                                <td>{{ $candidate->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-end">{{ number_format($candidate->risk_score, 3) }}</td>
                                <td><span class="badge text-bg-{{ $cColor }}">{{ $cText }}</span></td>
                                <td>
                                    @if ($candidate->maintenanceOrder)
                                        <a href="{{ route('maintenance-orders.show', $candidate->maintenanceOrder) }}">工單 #{{ $candidate->maintenance_order_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-3">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">報修紀錄</div>
                <div class="card-body text-muted small">
                    報修模組（彭仕衡主責）的分支尚未併入 develop，暫時無法串接實際資料。
                    待該分支併入後，這裡會改成唯讀查詢他的 Model 顯示這台設備的報修歷史，不會另外建表。
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">備品耗用成本</div>
                <div class="card-body text-muted small">
                    庫存／成本模組（劉家芸主責）的分支尚未併入 develop，暫時無法串接實際資料。
                    待該分支併入後，這裡會改成唯讀查詢他的 Model 顯示這台設備的備品耗用成本，不會另外建表。
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">附件</div>
                <div class="card-body text-muted small">
                    共用附件機制尚未建立（規劃在第 3 週任務 3 之後），暫時無法顯示附件。
                </div>
            </div>
        </div>
    </div>
@endsection
