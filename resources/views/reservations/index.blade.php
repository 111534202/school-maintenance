@extends('layouts.app')

@section('title', '空間預約')

@section('content')
@include('partials.theme-b-assets')
@php($currentStatus = request('status'))
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">空間預約</h1>
            <p class="tb-subtitle">查看預約申請，或提出新的教室借用申請。</p>
        </div>

        <a href="{{ route('reservations.create') }}" class="tb-btn tb-btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            新增預約
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert">
            <div class="fw-bold mb-1">操作未完成，請檢查以下內容：</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 申請狀態篩選：三張統計卡同時是篩選按鈕；主控台「空間預約待審核」卡片會連到「未審核」。 --}}
    <nav class="tb-stats" aria-label="申請狀態篩選">
        <a href="{{ route('reservations.index') }}" class="tb-stat {{ in_array($currentStatus, ['pending', 'reviewed'], true) ? '' : 'is-active' }}"
            @if (! in_array($currentStatus, ['pending', 'reviewed'], true)) aria-current="page" @endif>
            <div class="tb-stat-label">全部申請</div>
            <div class="tb-stat-value">{{ $counts['all'] }}</div>
        </a>
        <a href="{{ route('reservations.index', ['status' => 'pending']) }}" class="tb-stat {{ $currentStatus === 'pending' ? 'is-active' : '' }}"
            @if ($currentStatus === 'pending') aria-current="page" @endif>
            <div class="tb-stat-label">未審核</div>
            <div class="tb-stat-value tb-c-warn">{{ $counts['pending'] }}</div>
        </a>
        <a href="{{ route('reservations.index', ['status' => 'reviewed']) }}" class="tb-stat {{ $currentStatus === 'reviewed' ? 'is-active' : '' }}"
            @if ($currentStatus === 'reviewed') aria-current="page" @endif>
            <div class="tb-stat-label">已審核</div>
            <div class="tb-stat-value tb-c-accent">{{ $counts['reviewed'] }}</div>
        </a>
    </nav>

    @if ($reservations->isEmpty() && in_array($currentStatus, ['pending', 'reviewed'], true))
        <div class="tb-card tb-empty">沒有{{ $currentStatus === 'pending' ? '未審核' : '已審核' }}的申請。</div>
    @elseif ($reservations->isEmpty())
        <div class="tb-card tb-empty">
            <i class="bi bi-calendar-x" aria-hidden="true"></i>
            <h2 class="h5 mt-3" style="color: var(--tb-text)">目前沒有預約紀錄</h2>
            <p class="mb-3">可以點選「新增預約」提出申請。</p>
            <a href="{{ route('reservations.create') }}" class="tb-btn tb-btn-outline">新增預約</a>
        </div>
    @else
        {{-- 點這一行的任何地方都會進入預約詳細頁（預約日期、時段、用途、審核結果都在詳細頁）。 --}}
        <div class="tb-table-wrap">
            <div class="table-responsive">
                <table class="tb-table tb-table-hover reservation-rows" data-onion-primary="3,4">
                    <thead>
                        <tr>
                            <th>申請時間</th>
                            <th>申請人</th>
                            <th>申請班級</th>
                            <th>申請狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            @php($requesterName = $reservation->requester?->name ?? '使用者資料不存在')
                            <tr class="reservation-row" data-href="{{ route('reservations.show', $reservation) }}" tabindex="0" role="link"
                                aria-label="查看 {{ $reservation->class_name }} 的預約申請">
                                <td class="tb-num tb-muted text-nowrap">{{ $reservation->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <span class="tb-avatar" aria-hidden="true">{{ mb_substr($requesterName, 0, 1) }}</span>
                                        {{ $requesterName }}
                                    </span>
                                </td>
                                <td class="fw-bold">{{ $reservation->class_name }}</td>
                                <td>
                                    @if ($reservation->isReviewed())
                                        <span class="tb-pill tb-pill-ok">已審核</span>
                                    @else
                                        <span class="tb-pill tb-pill-warn">未審核</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $reservations->links() }}</div>
    @endif
</div>
<style>
    .reservation-row { cursor: pointer; }
    .reservation-row:focus-visible { outline: 2px solid var(--tb-accent); outline-offset: -2px; }
</style>
<script>
// 整行可點：點任何一格（或按 Enter）都進入預約詳細頁。
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reservation-row').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea')) { return; }
            if (window.getSelection().toString()) { return; }
            window.location.href = row.dataset.href;
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { window.location.href = row.dataset.href; }
        });
    });
});
</script>
@endsection
