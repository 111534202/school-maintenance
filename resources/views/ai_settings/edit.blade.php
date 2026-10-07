@extends('layouts.app')

@section('title', 'AI 預防保養設定')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">AI 預防保養設定</h1>
        <a href="{{ route('preventive-candidates.index') }}" class="btn btn-outline-secondary btn-sm">返回候選列表</a>
    </div>

    <form method="POST" action="{{ route('ai-settings.update') }}" class="card" style="max-width: 640px;">
        @csrf
        @method('PUT')
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
                <label for="require_approval" class="form-label">建立工單前是否需要主管審核</label>
                <select name="require_approval" id="require_approval" class="form-select">
                    <option value="1" @selected((string) old('require_approval', $settings['require_approval'] ? '1' : '0') === '1')>需要（先產生候選，核准後才建單）</option>
                    <option value="0" @selected((string) old('require_approval', $settings['require_approval'] ? '1' : '0') === '0')>不需要（達門檻直接建立 AI 工單）</option>
                </select>
            </div>

            <div class="alert alert-secondary small mb-0">
                以上預設值皆為工程實作決定，待全組確認；調整後下一次掃描立即生效，已產生的候選不受影響（候選會保留當時的分數與門檻）。
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">儲存設定</button>
        </div>
    </form>
@endsection
