@extends('layouts.app')

@section('title', '保養紀錄')

@section('content')
    @php [$statusText, $statusColor] = $maintenanceOrder->statusLabel(); @endphp

    {{-- 在對話框裡打開時只有標題隱藏（跟對話框標題重複）；「回報保養結果」「修改日期」「取消工單」都有 data-modal-header，
         會被搬到對話框標題列（✕ 左邊），修改日期、取消工單是圓形圖示按鈕（跟修改用戶的標題列一樣）。 --}}
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
                @if (! $maintenanceOrder->result && in_array($maintenanceOrder->status, [\App\Models\MaintenanceOrder::STATUS_PENDING, \App\Models\MaintenanceOrder::STATUS_IN_PROGRESS], true))
                    <a href="{{ route('maintenance-orders.edit', $maintenanceOrder) }}" class="btn btn-outline-primary btn-sm icon-btn" data-modal-header
                        data-modal-frame data-modal-title="修改保養日期" title="修改日期" aria-label="修改日期"><i class="bi bi-pencil"></i></a>
                @endif
                @if (! $maintenanceOrder->result && in_array($maintenanceOrder->status, [\App\Models\MaintenanceOrder::STATUS_PENDING, \App\Models\MaintenanceOrder::STATUS_IN_PROGRESS], true))
                    <form method="POST" action="{{ route('maintenance-orders.destroy', $maintenanceOrder) }}" data-modal-header
                        data-confirm="請問確定要取消保養工單 {{ $maintenanceOrder->displayNumber() }} 嗎？取消後會從列表與統計中移除，操作紀錄仍會保留。" data-confirm-ok="確認取消">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" title="取消工單" aria-label="取消工單"><i class="bi bi-x-circle"></i></button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if ($maintenanceOrder->status === \App\Models\MaintenanceOrder::STATUS_ABORTED)
        <div class="alert alert-dark d-flex gap-2" role="status">
            <i class="bi bi-slash-circle mt-1"></i>
            <div>這張保養工單已中止：設備已淘汰，沒做完的工單一併中止。資料已定案，只能查看，不能再修改、指派或回報結果。</div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">工單編號</dt>
                <dd class="col-sm-9"><span class="badge text-bg-{{ $maintenanceOrder->sourceColor() }}">{{ $maintenanceOrder->displayNumber() }}</span></dd>

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
                <dd class="col-sm-9">
                    <span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span>
                    @if ($maintenanceOrder->status === \App\Models\MaintenanceOrder::STATUS_CONVERTED)
                        <span class="text-muted small ms-2">轉出的報修單全部結案（已結案或已退回）後，工單會自動改成已完成。</span>
                    @endif
                </dd>

                <dt class="col-sm-3">排定保養日期</dt>
                <dd class="col-sm-9">
                    {{ $maintenanceOrder->scheduled_date?->format('Y-m-d') ?? '—' }}
                    @if ($maintenanceOrder->isOverdue())
                        <span class="badge text-bg-danger ms-1">逾期 {{ $maintenanceOrder->scheduled_date->diffInDays(now()->startOfDay(), true) }} 天</span>
                    @endif
                </dd>

                <dt class="col-sm-3">負責人</dt>
                <dd class="col-sm-9">
                    {{-- 待處理：保養管理者選負責人按「確認派工」→ 進行中，負責人才能回報結果。
                         進行中：保養管理者可以改派（負責人必填）。只能派給有保養回報權限、帳號啟用中的人。 --}}
                    @php $canDispatch = in_array($maintenanceOrder->status, [\App\Models\MaintenanceOrder::STATUS_PENDING, \App\Models\MaintenanceOrder::STATUS_IN_PROGRESS], true) && auth()->user()->can('maintenance.manage'); @endphp
                    @if ($canDispatch)
                        <form method="POST" action="{{ route('maintenance-orders.assign', $maintenanceOrder) }}" class="d-flex flex-wrap gap-2 align-items-center">
                            @csrf
                            @method('PATCH')
                            <select name="assigned_to" class="form-select form-select-sm w-auto @error('assigned_to') is-invalid @enderror" aria-label="負責人" required>
                                <option value="">（請選擇負責人）</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((int) old('assigned_to', $maintenanceOrder->assigned_to) === $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                            @if ($maintenanceOrder->status === \App\Models\MaintenanceOrder::STATUS_PENDING)
                                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-person-check me-1"></i>確認派工</button>
                            @else
                                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-left-right me-1"></i>改派</button>
                            @endif
                            @error('assigned_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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

                    <dt class="col-sm-3">說明</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $maintenanceOrder->result->note ?? '—' }}</dd>

                    @if ($maintenanceOrder->result->isNg())
                        <dt class="col-sm-3">轉出的報修單</dt>
                        <dd class="col-sm-9">
                            @forelse ($maintenanceOrder->result->repairRequests as $ngRepair)
                                <div>
                                    @can('view', $ngRepair)
                                        <a href="{{ route('repairs.show', $ngRepair) }}" data-modal-frame data-modal-title="報修案件" data-modal-size="xl">#{{ $ngRepair->id }} {{ $ngRepair->title }}</a>
                                    @else
                                        #{{ $ngRepair->id }} {{ $ngRepair->title }}
                                    @endcan
                                    @include('repairs._status-badge', ['status' => $ngRepair->status])
                                </div>
                            @empty
                                {{-- 設備已淘汰或停用：不能報修，沒有轉報修單。 --}}
                                @if ($maintenanceOrder->result->ng_conversion_status === \App\Models\MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE)
                                    <span class="badge text-bg-secondary">不建立報修單</span> 設備已淘汰或停用，不能報修
                                @else
                                    —
                                @endif
                            @endforelse
                        </dd>
                    @endif
                </dl>
            </div>
        </div>
    @endif

    @if ($maintenanceOrder->maintenancePlan?->description)
        <div class="card mt-3">
            <div class="card-header bg-white form-section-title"><i class="bi bi-card-text me-2"></i>保養說明（來源計畫）</div>
            <div class="card-body" style="white-space: pre-line;">{{ $maintenanceOrder->maintenancePlan->description }}</div>
        </div>
    @endif
@endsection
