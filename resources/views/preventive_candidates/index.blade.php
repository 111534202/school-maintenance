@extends('layouts.app')

@section('title', 'AI 預防保養候選')

@section('content')
{{-- 風格 B：篩選放在卡片裡；表格／清單自動切換，整頁捲動、表頭固定；點一列在對話框打開該設備的履歷。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">AI 預防保養候選</h1>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('ai-settings.edit') }}" class="tb-btn tb-btn-outline" data-modal-frame data-modal-title="AI 設定" data-modal-size="lg">AI 設定</a>
            <form method="POST" action="{{ route('preventive-candidates.scan') }}">
                @csrf
                <button type="submit" class="tb-btn tb-btn-primary">立即掃描設備風險</button>
            </form>
        </div>
    </div>

    <div class="tb-card tb-card-pad small">
        目前設定：風險門檻 <strong>{{ number_format($settings['risk_threshold'], 2) }}</strong>、
        至少 {{ $settings['min_completed_orders'] }} 筆保養紀錄才評分、
        去重天數 {{ $settings['dedup_window_days'] }} 天、
        {{ $settings['require_approval'] ? '需主管審核後才建立工單' : '達門檻直接建立 AI 工單（不需審核）' }}。
        風險分數是依保養紀錄用固定規則計算的（規則式，非機器學習），展開「評分明細」可看到每一項怎麼算。
    </div>

    <div class="tb-card tb-card-pad">
    <form method="GET" class="filter-bar tb-form-row tb-filter" data-no-onion>
        <div>
            <label for="status" class="form-label small mb-1">狀態</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">全部</option>
                @foreach (['pending' => '待審核', 'approved' => '已核准', 'rejected' => '已駁回', 'auto_created' => '自動建單', 'duplicate' => '重複未建單'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @include('layouts.partials.filter-actions', ['reset' => route('preventive-candidates.index'), 'fields' => ['status']])
    </form>
    </div>

    @if ($candidates->isEmpty())
        <div class="tb-card tb-empty">目前沒有候選。按右上角「立即掃描設備風險」，或等每日排程自動掃描。</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover" data-onion-primary="1">
            <thead>
                <tr>
                    <th>設備</th>
                    <th class="text-end">風險分數</th>
                    <th>狀態</th>
                    <th>提出時間</th>
                    <th>評分明細</th>
                    <th>處理結果</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($candidates as $candidate)
                    @php [$statusText, $statusColor] = $candidate->statusLabel(); @endphp
                    <tr data-modal-frame-href="{{ route('device-profile.show', $candidate->device) }}" data-modal-title="設備履歷" data-modal-size="xl" tabindex="0" role="link">
                        <td>
                            <span class="fw-semibold">{{ $candidate->device->device_code }}</span>
                            <div class="text-muted small">{{ $candidate->device->brand }} {{ $candidate->device->model }}</div>
                        </td>
                        <td class="text-end">
                            {{ number_format($candidate->risk_score, 3) }}
                            <div class="text-muted small">門檻 {{ number_format($candidate->threshold, 2) }}</div>
                        </td>
                        <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                        <td>{{ $candidate->created_at?->format('Y-m-d H:i') }}</td>
                        <td style="white-space: normal;">
                            <details>
                                <summary class="small">展開（{{ $candidate->algorithm }}）</summary>
                                @include('preventive_candidates._explanation', ['explanation' => $candidate->explanation ?? []])
                            </details>
                        </td>
                        <td>
                            @if ($candidate->maintenanceOrder)
                                <a href="{{ route('maintenance-orders.show', $candidate->maintenanceOrder) }}">工單 #{{ $candidate->maintenance_order_id }}</a>
                            @endif
                            @if ($candidate->decided_at)
                                <div class="text-muted small">
                                    {{ $candidate->decider?->name ?? '系統' }}　{{ $candidate->decided_at->format('Y-m-d H:i') }}
                                </div>
                            @endif
                            @if ($candidate->decision_note)
                                <div class="small">{{ $candidate->decision_note }}</div>
                            @endif
                            @if (! $candidate->maintenanceOrder && ! $candidate->decided_at)
                                —
                            @endif
                        </td>
                        <td class="text-end tb-col-fit">
                            @if ($candidate->status === 'pending')
                                <form method="POST" action="{{ route('preventive-candidates.approve', $candidate) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">核准建單</button>
                                </form>
                                <form method="POST" action="{{ route('preventive-candidates.reject', $candidate) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">駁回</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $candidates])</div>
    @endif
</div>
@endsection
