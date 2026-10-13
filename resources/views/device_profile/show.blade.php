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
                <dd class="col-sm-9"><span class="badge {{ \App\Models\Device::statusBadgeClass($device->status) }}">{{ \App\Models\Device::statusLabel($device->status) }}</span></dd>

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

    {{-- 報修與維修紀錄：唯讀顯示彭仕衡模組的資料，只列出目前使用者有權限檢視的報修單。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>報修與維修紀錄</span>
            <span class="text-muted small">
                共 {{ $repairStats['total'] }} 張報修單、未結案 {{ $repairStats['open'] }} 張、維修工時合計 {{ number_format($repairStats['hours'], 2) }} 小時
            </span>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($repairRequests as $repair)
                @php
                    $statusColor = match ($repair->status->value) {
                        'pending' => 'secondary',
                        'assigned' => 'info',
                        'in_progress' => 'primary',
                        'pending_review' => 'warning',
                        'completed' => 'success',
                        default => 'light',
                    };
                    $impactText = ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'][$repair->impact_level] ?? $repair->impact_level;
                    $ngSource = $ngSources->get($repair->id);
                @endphp
                <div class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ route('repairs.show', $repair) }}" class="fw-semibold">#{{ $repair->id }} {{ $repair->title }}</a>
                            <span class="badge text-bg-{{ $statusColor }} ms-1">{{ $repair->status->label() }}</span>
                            <span class="badge text-bg-light border ms-1">影響：{{ $impactText }}</span>
                            @if ($ngSource)
                                <span class="badge text-bg-danger ms-1">保養 NG 轉入</span>
                            @endif
                        </div>
                        <span class="text-muted small">{{ $repair->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="text-muted small mt-1">
                        報修人：{{ $repair->reporter?->name ?? '系統轉入' }}
                        ／ 維修人員：{{ $repair->assignedTechnician?->name ?? $repair->assignee_note ?? '尚未指派' }}
                        @if ($ngSource?->maintenanceOrder)
                            ／ 來源：<a href="{{ route('maintenance-orders.results.show', $ngSource->maintenanceOrder) }}">保養工單 #{{ $ngSource->maintenance_order_id }} 的 NG 結果</a>
                        @endif
                    </div>

                    @forelse ($repair->repairLogs->sortBy('id')->values() as $index => $log)
                        <div class="border-start border-3 ps-3 mt-2 small">
                            <div class="fw-semibold">
                                維修紀錄 {{ $index + 1 }}
                                <span class="text-muted fw-normal">
                                    {{ $log->started_at?->format('Y-m-d H:i') ?? '—' }} ～ {{ $log->ended_at?->format('Y-m-d H:i') ?? '—' }}
                                    @if ($log->total_hours !== null)（{{ number_format((float) $log->total_hours, 2) }} 小時）@endif
                                </span>
                            </div>
                            <div>故障原因：{{ $log->cause ?? '—' }}</div>
                            <div>處置方式：{{ $log->resolution ?? '—' }}</div>
                            <div>使用備品：{{ $log->parts_used_note ?: '—' }}</div>
                            @if ($log->attachments->isNotEmpty())
                                <div>附件：{{ $log->attachments->count() }} 個（見下方「附件」）</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small mt-2">尚無維修紀錄</div>
                    @endforelse
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">這台設備目前沒有報修紀錄</div>
            @endforelse
        </div>
        @if ($hiddenRepairCount > 0)
            <div class="card-footer text-muted small">另有 {{ $hiddenRepairCount }} 張報修單因權限限制無法在此顯示。</div>
        @endif
    </div>

    <div class="row row-cols-1 row-cols-md-2 g-3">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">備品耗用成本</div>
                <div class="card-body text-muted small">
                    目前以維修紀錄上的「使用備品」文字說明呈現（見上方）。
                    庫存／成本模組（劉家芸主責）尚未併入 develop，備品金額待她的介面確認後再串接唯讀查詢，不會在這裡另外建表。
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">附件（{{ $attachments->count() }}）</div>
                @if ($attachments->isEmpty())
                    <div class="card-body text-muted small">這台設備的報修單與維修紀錄目前沒有附件。</div>
                @else
                    <ul class="list-group list-group-flush small">
                        @foreach ($attachments as $item)
                            <li class="list-group-item">
                                <a href="{{ $item['file']->url() }}" target="_blank" rel="noopener">{{ $item['file']->original_name }}</a>
                                <span class="text-muted">— {{ $item['from'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
