@extends('layouts.app')

@section('title', '設備履歷：'.$device->device_code)

@section('content')
{{-- $inModal：從設備履歷列表點一列，在對話框（標題「設備履歷：設備編號」）裡打開：只顯示基本資料＋「查看履歷」，
     保養紀錄、報修紀錄等完整內容按「查看履歷」換頁後才顯示。 --}}
@php $inModal = request()->boolean('modal'); @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 data-modal-hide class="tb-title mb-0">設備履歷：{{ $device->device_code }}</h1>
    </div>

    {{-- 外面多包一層：在對話框裡打開時，卡片的外框才不會被拿掉（對話框只拿掉直接放在內容區的卡片外框）。 --}}
    <div>
    <div class="card mb-3">
        @unless ($inModal)
            <div class="card-header"><h2 class="tb-section-title">基本資料</h2></div>
        @endunless
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
                <dd class="col-sm-9">{{ $device->warranty_end_date?->format('Y-m-d') ?? '—' }}</dd>

                <dt class="col-sm-3">所在教室</dt>
                <dd class="col-sm-9">
                    @if ($device->classroom)
                        {{ $device->classroom->classroom_code }} - {{ $device->classroom->name }}
                    @else
                        —
                    @endif
                </dd>

                <dt class="col-sm-3">狀態</dt>
                <dd class="col-sm-9"><span class="badge {{ \App\Models\Device::statusBadgeClass($device->display_status) }}">{{ \App\Models\Device::statusLabel($device->display_status) }}</span></dd>
            </dl>
        </div>
    </div>

    </div>

    @if ($inModal)
        <div class="d-flex justify-content-end">
            <a href="{{ route('device-profile.show', $device) }}" class="btn btn-primary">
                <i class="bi bi-journal-text me-1" aria-hidden="true"></i>查看履歷
            </a>
        </div>
    @else
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">保養紀錄</h2>
            <span class="text-muted small">
                共 {{ $maintenanceStats['total'] }} 筆、已完成 {{ $maintenanceStats['completed'] }} 筆
                （OK {{ $maintenanceStats['ok'] }} / NG {{ $maintenanceStats['ng'] }}）
            </span>
        </div>
        {{-- data-no-fit：表格檢視整張表都顯示，不限制高度。data-fit-per-page="5"：清單檢視固定一頁 5 筆。清單模式以排定日期當標題（data-onion-title）。 --}}
        <div class="table-responsive" data-no-fit data-fit-per-page="5">
            <table class="tb-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="text-center tb-col-fit">來源</th>
                        <th>來源計畫</th>
                        <th class="text-center">狀態</th>
                        <th data-onion-title>排定日期</th>
                        <th class="text-center">結果</th>
                        <th>執行人</th>
                        <th class="text-end tb-col-fit">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maintenanceOrders as $order)
                        @php [$statusText, $statusColor] = $order->statusLabel(); @endphp
                        <tr>
                            <td class="text-center tb-col-fit"><span class="badge text-bg-{{ $order->sourceColor() }}">{{ $order->sourceLabel() }}</span></td>
                            <td>{{ $order->maintenancePlan?->name ?? ($order->source === 'ai' ? 'AI 預防保養（無計畫）' : '—') }}</td>
                            <td class="text-center"><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                            <td>{{ $order->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-center">
                                @if ($order->result)
                                    @php [$resultText, $resultColor] = $order->result->resultLabel(); @endphp
                                    <span class="badge text-bg-{{ $resultColor }}">{{ $resultText }}</span>
                                @else
                                    <span class="text-muted">尚未回報</span>
                                @endif
                            </td>
                            <td>{{ $order->result->executor?->name ?? '—' }}</td>
                            <td class="text-end tb-col-fit">
                                <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="保養紀錄" data-modal-size="xl">詳細</a>
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
            <h2 class="tb-section-title">AI 風險評估</h2>
            {{-- 評分規則的版本代號（rule_based_v1＝規則式評分第 1 版），顯示成看得懂的文字。 --}}
            <span class="text-muted small">{{ filled($prediction['algorithm'] ?? null) ? '評分規則版本：' . preg_replace('/^rule_based_v/', 'v', $prediction['algorithm']) : '' }}</span>
        </div>
        <div class="card-body">
            @if ($prediction['risk_score'] === null)
                <p class="mb-0 text-muted">{{ $prediction['recommended_action'] }}</p>
            @else
                @php $overThreshold = $prediction['risk_score'] >= $prediction['threshold']; @endphp
                {{-- 風險分數這一列：上下間隔一樣（跟卡片上緣、下面的說明都是 16px），文字垂直置中。 --}}
                <p class="mb-3 d-flex flex-wrap align-items-center gap-1">
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
                <table class="tb-table tb-table-sm mb-0 align-middle">
                    <thead>
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

    {{-- 維修紀錄：唯讀顯示彭仕衡模組的資料，只列出目前使用者有權限檢視的報修單（已退回的不列）。
         跟上面的保養紀錄一樣用表格／清單（清單檢視固定一頁 5 筆）；故障原因、處置方式等完整內容按「詳細」在對話框打開報修案件看。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">維修紀錄</h2>
            {{-- 沒有任何報修單（全部是 0）就不顯示統計。 --}}
            @if ($repairStats['total'] > 0)
                <span class="text-muted small">
                    共 {{ $repairStats['total'] }} 張報修單、工時累計 {{ number_format($repairStats['hours'], 2) }} 小時
                </span>
            @endif
        </div>
        <div class="table-responsive" data-no-fit data-fit-per-page="5">
            <table class="tb-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th data-onion-title>報修單</th>
                        <th class="text-center">狀態</th>
                        <th class="text-center">影響程度</th>
                        <th>送出時間</th>
                        <th>維修人員</th>
                        <th class="text-end">工時</th>
                        <th class="text-end tb-col-fit">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($repairRequests as $repair)
                        @php
                            $ngSource = $ngSources->get($repair->id);
                            $hours = (float) $repair->repairLogs->sum('total_hours');
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">#{{ $repair->id }} {{ $repair->title }}</span>
                                {{-- 保養 NG 轉入的報修單：標籤可以點，在對話框打開來源的保養結果。 --}}
                                @if ($ngSource?->maintenanceOrder)
                                    <a href="{{ route('maintenance-orders.results.show', $ngSource->maintenanceOrder) }}" class="badge text-bg-danger text-decoration-none ms-1"
                                        data-modal-frame data-modal-title="保養結果" data-modal-size="lg" title="保養工單 #{{ $ngSource->maintenance_order_id }} 的 NG 結果">保養 NG 轉入</a>
                                @elseif ($ngSource)
                                    <span class="badge text-bg-danger ms-1">保養 NG 轉入</span>
                                @endif
                            </td>
                            <td class="text-center">@include('repairs._status-badge', ['status' => $repair->status])</td>
                            <td class="text-center"><span class="badge {{ ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repair->impact_level] ?? 'text-bg-secondary' }}">{{ __('repair_requests.impact_level.' . $repair->impact_level) }}</span></td>
                            <td>{{ $repair->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $repair->assignedTechnician?->name ?? '尚未指派' }}</td>
                            <td class="text-end">{{ $repair->repairLogs->isNotEmpty() ? number_format($hours, 2) . ' 小時' : '—' }}</td>
                            <td class="text-end tb-col-fit">
                                <a href="{{ route('repairs.show', $repair) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="報修案件" data-modal-size="xl">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">這台設備目前沒有維修紀錄</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($hiddenRepairCount > 0)
            <div class="card-footer text-muted small">另有 {{ $hiddenRepairCount }} 張報修單因權限限制無法在此顯示。</div>
        @endif
    </div>

    <div class="row row-cols-1 row-cols-md-2 g-3">
        <div class="col">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="tb-section-title">備品耗用成本</h2>
                    @if ($canSeePartCost && $partUsage->isNotEmpty())
                        <span class="text-muted small">合計 {{ number_format($partUsage->sum('amount')) }} 元</span>
                    @endif
                </div>
                {{-- 這台設備的維修紀錄、保養結果正式登記的零件（同一個零件合併數量）；金額只給零件庫存管理者看。
                     維修紀錄裡只用文字寫的「使用備品說明」沒有登記成正式零件，列在下面「其他備註」，沒有金額。 --}}
                @if ($partUsage->isEmpty() && $partNotes->isEmpty())
                    <div class="card-body text-muted small">這台設備目前沒有備品耗用紀錄。</div>
                @else
                    @if ($partUsage->isNotEmpty())
                        <div class="table-responsive" data-no-fit data-no-onion>
                            <table class="tb-table tb-table-sm mb-0 align-middle" data-no-onion>
                                <thead>
                                    <tr>
                                        <th>零件</th>
                                        <th class="text-end">數量</th>
                                        @if ($canSeePartCost)<th class="text-end">金額</th>@endif
                                        <th>來源</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($partUsage as $row)
                                        <tr>
                                            <td>{{ $row['part']?->name ?? '（已刪除的零件）' }}</td>
                                            <td class="text-end">{{ $row['quantity'] }} {{ $row['part']?->unit }}</td>
                                            @if ($canSeePartCost)<td class="text-end">{{ number_format($row['amount']) }}</td>@endif
                                            <td class="small text-muted">{{ $row['sources']->implode('、') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if ($partNotes->isNotEmpty())
                        <div class="card-body small {{ $partUsage->isNotEmpty() ? 'border-top' : '' }}">
                            <div class="text-muted mb-1">其他備註（維修紀錄的使用備品說明，沒有登記成正式零件）</div>
                            @foreach ($partNotes as $note)
                                <div>{{ $note['note'] }}<span class="text-muted">（{{ $note['source'] }}）</span></div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header"><h2 class="tb-section-title">附件（{{ $attachments->count() }}）</h2></div>
                @if ($attachments->isEmpty())
                    <div class="card-body text-muted small">這台設備的報修單與維修紀錄目前沒有附件。</div>
                @else
                    {{-- 同一張報修單的附件（報修時上傳的＋維修紀錄的前後照片）合成一顆灰色橢圓按鈕「📎 報修單 #14　4 個檔案」；
                         點了在目前畫面的預覽視窗打開這張單的全部附件（不換頁），可以左右切換。 --}}
                    <div class="card-body d-flex flex-wrap align-items-start align-content-start gap-2">
                        @foreach (collect($attachments)->groupBy(fn ($item) => $item['repair']->id) as $repairId => $group)
                            @php $previewItems = $group->map(fn ($item) => ['url' => $item['file']->url(), 'mime' => $item['file']->mime_type, 'filename' => $item['file']->file_name])->values()->toJson(); @endphp
                            <a href="{{ $group->first()['file']->url() }}" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#attachmentModal"
                                data-title="報修單 #{{ $repairId }} 的附件" data-attachments="{{ $previewItems }}" data-index="0"><i class="bi bi-paperclip me-1"></i>報修單 #{{ $repairId }}<span class="text-muted ms-2">{{ $group->count() }} 個檔案</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    @include('parts._attachment-modal')
    @endif
@endsection
