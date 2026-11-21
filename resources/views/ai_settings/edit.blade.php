@extends('layouts.app')

@section('title', 'AI 預防保養設定')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 data-modal-hide class="tb-title mb-0">AI 預防保養設定</h1>
    </div>

    <form method="POST" action="{{ route('ai-settings.update') }}">
        @csrf
        @method('PUT')
        {{-- 欄位放在細線外框裡；「儲存設定」在外框外面、靠右，上面不加分隔線。 --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white form-section-title"><i class="bi bi-sliders me-2"></i>AI 參數</div>
            <div class="card-body">
            <div class="mb-3">
                <label for="risk_threshold" class="form-label">風險分數門檻（0.05 ~ 1）</label>
                <input type="number" step="0.01" min="0.05" max="1" name="risk_threshold" id="risk_threshold"
                       class="form-control" value="{{ old('risk_threshold', $settings['risk_threshold']) }}" required>
                <div class="form-text">風險分數大於等於此值，就會提出預防保養候選。越低越敏感。</div>
            </div>

            <div class="mb-3">
                <label for="min_completed_orders" class="form-label">最少保養紀錄筆數</label>
                <input type="number" min="1" max="24" name="min_completed_orders" id="min_completed_orders"
                       class="form-control" value="{{ old('min_completed_orders', $settings['min_completed_orders']) }}" required>
                <div class="form-text">已回報結果的保養紀錄少於這個筆數，視為資料不足，不評分也不提出候選。</div>
            </div>

            <div class="mb-3">
                <label for="recent_window" class="form-label">評分只看最近幾筆保養結果</label>
                <input type="number" min="2" max="24" name="recent_window" id="recent_window"
                       class="form-control" value="{{ old('recent_window', $settings['recent_window']) }}" required>
                <div class="form-text">用來計算 NG 比例的最近筆數。</div>
            </div>

            <div class="mb-3">
                <label for="dedup_window_days" class="form-label">去重天數（0 ~ 180）</label>
                <input type="number" min="0" max="180" name="dedup_window_days" id="dedup_window_days"
                       class="form-control" value="{{ old('dedup_window_days', $settings['dedup_window_days']) }}" required>
                <div class="form-text">這幾天內已有（或即將到期）的保養工單、近期建立過的 AI 工單、剛被駁回的候選，都不會重複提出。</div>
            </div>

            <div class="mb-3">
                <label for="schedule_lead_days" class="form-label">預設排定在幾天後（0 ~ 60）</label>
                <input type="number" min="0" max="60" name="schedule_lead_days" id="schedule_lead_days"
                       class="form-control" value="{{ old('schedule_lead_days', $settings['schedule_lead_days']) }}" required>
                <div class="form-text">核准候選時，「排定保養日期」會先帶入今天加上這個天數，主管可以自己改；選「不需要審核」時，系統就用這個天數自動排定。設 0 代表排定當天。</div>
            </div>

            <div class="mb-3">
                <label for="require_approval" class="form-label">建立工單前是否需要主管審核</label>
                <select name="require_approval" id="require_approval" class="form-select">
                    <option value="1" @selected((string) old('require_approval', $settings['require_approval'] ? '1' : '0') === '1')>需要（先產生候選，核准後才建單）</option>
                    <option value="0" @selected((string) old('require_approval', $settings['require_approval'] ? '1' : '0') === '0')>不需要（達門檻直接建立 AI 工單）</option>
                </select>
            </div>

            <div class="text-muted small mb-3">
                調整後下一次掃描立即生效，已產生的候選不受影響（候選會保留當時的分數與門檻）。
            </div>
            <details class="small">
                <summary class="fw-semibold">風險分數怎麼算</summary>
                <div class="mt-2">
                    每天凌晨 2 點，系統替每台「正常」的設備算一個 0 到 1 的風險分數，分數越高越可能出問題：
                    <ul class="mb-2 mt-1">
                        <li><b>NG 比例（占 50%）</b>：最近幾筆保養結果裡，NG 占多少。例如最近 5 筆有 2 筆 NG，是 0.4，貢獻 0.4 × 0.5 = 0.20。</li>
                        <li><b>連續 NG（占 30%）</b>：從最近一次往前，連續 NG 幾次（最多算 3 次）。連續 2 次 NG 是 2 ÷ 3，貢獻 0.67 × 0.3 = 0.20。</li>
                        <li><b>保養逾期（占 20%）</b>：距上次保養 45 天內算 0，90 天以上算 1，中間按天數平均增加。距上次 67.5 天是 0.5，貢獻 0.10。</li>
                    </ul>
                    三項加起來就是風險分數（上面例子 0.20 + 0.20 + 0.10 = 0.50）。分數大於等於上面的「風險分數門檻」就提出候選，每筆候選都能展開看這三項的明細。
                    這是規則式評分，不是機器學習，沒有黑箱，同一份紀錄算出的分數永遠相同。
                </div>
            </details>
        </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>儲存設定</button>
        </div>
    </form>
@endsection
