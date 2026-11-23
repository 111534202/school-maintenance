@extends('layouts.app')

@section('title', 'AI 預防保養候選')

@section('content')
@php $leadDays = (int) \App\Models\AiSetting::get(\App\Models\AiSetting::SCHEDULE_LEAD_DAYS); @endphp
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
        系統依設備的保養紀錄算出風險分數，分數達到門檻
        {{ $settings['require_approval'] ? '就會產生一筆待審核候選，由主管決定要不要建立保養工單' : '就會直接建立保養工單' }}。
        <details class="mt-1">
            <summary>這個功能在做什麼？分數怎麼算？（點我展開）</summary>
            <div class="mt-2">
                <p class="mb-2">
                    <strong>這是什麼：</strong>
                    系統會檢查每一台「正常」狀態的設備過去的<strong>保養紀錄</strong>，算出一個 0～1 的「風險分數」。
                    分數越高，代表這台設備最近保養時越常被標記異常（NG）、或是已經很久沒保養了，
                    值得優先安排一次預防性保養，而不是放著不管、等到真的故障才處理。
                    可以按右上角「立即掃描設備風險」手動掃描一次，平常則是每天自動掃描。
                </p>
                <p class="mb-2">
                    <strong>分數怎麼算：</strong>
                    風險分數 ＝ <code>0.5 × 最近幾筆保養的 NG 比例</code>
                    ＋ <code>0.3 × 結尾連續 NG 次數（最多計到 3 次）</code>
                    ＋ <code>0.2 × 保養逾期程度（距上次保養 45 天內不計分，90 天以上計滿分，中間照比例）</code>。
                    這是<strong>固定公式</strong>算出來的（規則式），<strong>不是訓練出來的機器學習模型</strong>——
                    同一份保養紀錄、同一天算，結果一定一樣，每一項的原始數值、權重、貢獻分數都攤開在表格的「評分明細」裡可以展開查看。
                    目前只看「保養紀錄」，還沒把報修／維修次數算進去。
                </p>
                <p class="mb-2">
                    <strong>分數達到門檻之後會怎樣：</strong>
                    @if ($settings['require_approval'])
                        不會自動建單，而是先產生一筆「<span class="badge text-bg-warning">待審核</span>」候選，
                        要主管按「核准建單」才會正式建立保養工單；按「駁回」則這台設備這次不建單。
                    @else
                        系統會直接建立一筆「AI 來源」的保養工單，不需要人工審核，
                        這筆候選會標成「<span class="badge text-bg-info">自動建單</span>」，只是留做紀錄方便追蹤。
                    @endif
                    如果這台設備保養紀錄太少、分數還沒到門檻，或是短期內已經有其他保養工單在處理同一台設備，
                    這次掃描就會直接略過、不會產生候選（掃描完成後的訊息會列出各種略過的數量）。
                </p>
                <p class="mb-2">
                    <strong>狀態欄位意思：</strong>
                    <span class="badge text-bg-warning">待審核</span> 等主管決定
                    <span class="badge text-bg-success">已核准</span> 已建立保養工單
                    <span class="badge text-bg-secondary">已駁回</span> 主管決定這次不處理
                    <span class="badge text-bg-info">自動建單</span> 設定為不需審核，系統直接建單
                    <span class="badge text-bg-dark">重複未建單</span> 核准當下發現已經有其他工單在處理，所以沒有真的建單。
                </p>
                <p class="mb-0">
                    <strong>目前設定：</strong>風險門檻 <strong>{{ number_format($settings['risk_threshold'], 2) }}</strong>
                    （分數達到或超過這個值才會建議）、
                    至少要有 {{ $settings['min_completed_orders'] }} 筆已完成的保養紀錄才會評分（資料太少無法判斷趨勢）、
                    去重天數 {{ $settings['dedup_window_days'] }} 天（這段時間內已經有保養工單在處理的設備不會重複提醒）、
                    目前{{ $settings['require_approval'] ? '需要主管審核後才建立工單' : '達門檻就直接建立工單，不需審核' }}。
                    這些數字可以在右上角「AI 設定」調整。
                </p>
            </div>
        </details>
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
        <table class="tb-table tb-table-hover pc-table" data-onion-primary="1">
            <thead>
                <tr>
                    <th>設備</th>
                    <th class="text-end">風險分數</th>
                    <th>狀態</th>
                    <th>提出時間</th>
                    <th>評分明細</th>
                    <th>處理結果</th>
                    <th class="text-end pc-col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($candidates as $candidate)
                    @php [$statusText, $statusColor] = $candidate->statusLabel(); @endphp
                    <tr data-modal-frame-href="{{ route('device-profile.show', $candidate->device) }}" data-modal-title="設備履歷" data-modal-size="xl" tabindex="0" role="link">
                        <td data-label="設備">
                            <span class="fw-semibold">{{ $candidate->device->device_code }}</span>
                            <div class="text-muted small">{{ $candidate->device->brand }} {{ $candidate->device->model }}</div>
                        </td>
                        <td class="text-end" data-label="風險分數">
                            {{ number_format($candidate->risk_score, 3) }}
                            <div class="text-muted small">門檻 {{ number_format($candidate->threshold, 2) }}</div>
                        </td>
                        <td data-label="狀態"><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                        <td data-label="提出時間">{{ $candidate->created_at?->format('Y-m-d H:i') }}</td>
                        <td style="white-space: normal;" data-label="評分明細">
                            <details>
                                <summary class="small">展開（{{ $candidate->algorithm }}）</summary>
                                @include('preventive_candidates._explanation', ['explanation' => $candidate->explanation ?? []])
                            </details>
                        </td>
                        <td data-label="處理結果">
                            @if ($candidate->maintenanceOrder)
                                <a href="{{ route('maintenance-orders.show', $candidate->maintenanceOrder) }}">工單 {{ $candidate->maintenanceOrder->displayNumber() }}</a>
                                @if ($candidate->maintenanceOrder->scheduled_date)
                                    <div class="text-muted small">排定 {{ $candidate->maintenanceOrder->scheduled_date->format('Y-m-d') }}</div>
                                @endif
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
                        <td class="text-end pc-col-actions" data-label="操作">
                            @if ($candidate->status === 'pending')
                            <div class="pc-actions">
                                @if (! \App\Models\Device::canBeMaintained($candidate->device_id))
                                    {{-- 設備已淘汰或停用：不能再核准建單，只能駁回。 --}}
                                    <span class="text-muted small pc-note">設備已淘汰或停用，不能建立保養工單</span>
                                @endif
                                @if (\App\Models\Device::canBeMaintained($candidate->device_id))
                                <form method="POST" action="{{ route('preventive-candidates.approve', $candidate) }}" class="pc-approve">
                                    @csrf
                                    {{-- 排定保養日期：由主管選（預設是今天加上 AI 設定的預設天數，不能選過去的日子）。 --}}
                                    <label class="visually-hidden" for="scheduled_date_{{ $candidate->id }}">排定保養日期</label>
                                    <input type="date" id="scheduled_date_{{ $candidate->id }}" name="scheduled_date" class="form-control form-control-sm w-auto"
                                           value="{{ now()->addDays($leadDays)->toDateString() }}" min="{{ now()->toDateString() }}" required>
                                    {{-- 設備主檔記的設備位置（教室）的一週課表：按一下挑沒課的日子。 --}}
                                    @if ($candidate->device?->classroom)
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#timetableModal"
                                                data-target-input="scheduled_date_{{ $candidate->id }}" data-url="{{ route('devices.timetable', $candidate->device) }}"
                                                data-classroom-name="{{ $candidate->device->classroom->name }}（{{ $candidate->device->classroom->classroom_code }}）" title="看 {{ $candidate->device->classroom->name }} 的課表挑日期">
                                            <i class="bi bi-calendar-week"></i><span class="visually-hidden">看課表挑日期</span>
                                        </button>
                                    @endif
                                    <button type="submit" class="btn btn-sm btn-success">核准建單</button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('preventive-candidates.reject', $candidate) }}" class="pc-reject">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">駁回</button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $candidates])</div>
    {{-- 教室課表視窗：每一列的課表按鈕各帶自己那間教室的網址（見 data-url）。 --}}
    @include('layouts.partials.classroom-timetable-modal', ['classroom' => (object) ['name' => '教室', 'classroom_code' => ''], 'url' => '', 'mode' => 'date', 'busyLabel' => '同教室已排保養', 'busyHint' => '日期標題下方會列出同一間教室其他設備已排的保養工單'])
    @endif
</div>
<style>
    /* 「操作」欄：日期、課表鈕、核准建單、駁回都放在同一個 .pc-actions 裡，空間不夠時整個按鈕換行，絕不被表格外框裁掉。
       （原本欄位是 white-space: nowrap 又只有 1% 寬，核准表單被擠成兩行，後面的「駁回」就被推出表格外面。） */
    .pc-table > thead > tr > th.pc-col-actions,
    .pc-table > tbody > tr > td.pc-col-actions { width: 1%; min-width: 25rem; white-space: normal; }
    .pc-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .4rem; }
    .pc-actions form { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .4rem; margin: 0; }
    .pc-actions .pc-note { flex: 1 0 100%; text-align: right; }
    .pc-actions input[type=date] { width: auto; max-width: 100%; }
    .pc-actions .btn { white-space: nowrap; }

    /* 窄畫面（手機、平板，小於 1100px，跟全站表格／清單自動切換的門檻一樣）：表格裡有填日期的欄位，洋蔥清單不會轉它，
       所以這一頁自己把每一列排成一張直式卡片，不左右捲動。 */
    @media (max-width: 1099.98px) {
        .pc-table > thead { display: none; }
        .pc-table, .pc-table > tbody, .pc-table > tbody > tr { display: block; width: 100%; }
        .pc-table > tbody > tr { padding: .75rem 1rem; border-bottom: 1px solid var(--tb-border, #dee2e6); }
        .pc-table > tbody > tr:last-child { border-bottom: 0; }
        .tb-body .pc-table > tbody > tr > td { display: block; width: auto; padding: .3rem 0; text-align: left !important; background: transparent !important; }
        .pc-table > tbody > tr > td::before { content: attr(data-label); display: block; font-size: .8rem; color: var(--tb-muted, #6c757d); }
        .pc-table > tbody > tr > td:first-child { font-weight: normal; }
        .tb-body .pc-table > tbody > tr > td.pc-col-actions { min-width: 0; width: auto; padding-top: .6rem; }
        .pc-table > tbody > tr > td.pc-col-actions:not(:has(.pc-actions)) { display: none; }
        .pc-actions, .pc-actions form { justify-content: flex-start; }
        .pc-actions .pc-note { text-align: left; }
    }
</style>
@endsection
