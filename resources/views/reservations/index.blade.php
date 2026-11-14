@extends('layouts.app')

@section('title', '空間申請')

@section('content')
@php
    $currentStatus = request('status');
    // 目前的搜尋條件（切換統計卡時保留）。
    $search = array_filter(request()->only(['keyword']), 'filled');
@endphp
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">空間申請</h1>
        </div>
        {{-- 右上角：「我的申請」在畫面中的對話框打開自己的申請（表格／清單自動切換）；「空間預約」換到有課表的申請頁。 --}}
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('reservations.mine') }}" class="tb-btn tb-btn-outline" data-modal-frame data-modal-table data-modal-title="我的申請"><i class="bi bi-person-lines-fill" aria-hidden="true"></i>我的申請</a>
            <a href="{{ route('reservations.create') }}" class="tb-btn tb-btn-primary"><i class="bi bi-calendar-plus" aria-hidden="true"></i>空間預約</a>
        </div>
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

    {{-- 搜尋：跟零件庫存一樣只有一個輸入框，自己輸入任何一段文字（申請狀態、送出時間、教室、申請班級、申請人、申請時段）。
         搜尋框和按鈕不論寬窄都在同一行。 --}}
    <form method="GET" action="{{ route('reservations.index') }}" class="tb-form-row tb-form-row-nowrap" role="search">
        @if (in_array($currentStatus, ['pending', 'reviewed'], true))
            <input type="hidden" name="status" value="{{ $currentStatus }}">
        @endif
        <label for="keyword" class="visually-hidden">搜尋（申請狀態／送出時間／教室／申請班級／申請人／申請時段）</label>
        <input type="text" name="keyword" id="keyword" class="tb-input tb-input-pill" style="flex: 1 1 0; min-width: 0"
            value="{{ request('keyword') }}" placeholder="搜尋申請狀態、送出時間、教室、申請班級、申請人或申請時段，例如 待審核、2026-11-06、第 6 節">
        <button type="submit" class="tb-btn tb-btn-dark">搜尋</button>
        @if ($search)
            <a href="{{ route('reservations.index', array_filter(['status' => in_array($currentStatus, ['pending', 'reviewed'], true) ? $currentStatus : null])) }}" class="tb-btn tb-btn-outline">清除</a>
        @endif
    </form>

    {{-- 申請狀態篩選：三張統計卡同時是篩選按鈕（保留上面的搜尋條件）；主控台「空間預約待審核」卡片會連到「未審核」。 --}}
    <nav class="tb-stats" aria-label="申請狀態篩選">
        <a href="{{ route('reservations.index', $search) }}" class="tb-stat {{ in_array($currentStatus, ['pending', 'reviewed'], true) ? '' : 'is-active' }}"
            @if (! in_array($currentStatus, ['pending', 'reviewed'], true)) aria-current="page" @endif>
            <div class="tb-stat-label">全部申請</div>
            <div class="tb-stat-value">{{ $counts['all'] }}</div>
        </a>
        <a href="{{ route('reservations.index', $search + ['status' => 'pending']) }}" class="tb-stat {{ $currentStatus === 'pending' ? 'is-active' : '' }}"
            @if ($currentStatus === 'pending') aria-current="page" @endif>
            <div class="tb-stat-label">未審核</div>
            <div class="tb-stat-value tb-c-warn">{{ $counts['pending'] }}</div>
        </a>
        <a href="{{ route('reservations.index', $search + ['status' => 'reviewed']) }}" class="tb-stat {{ $currentStatus === 'reviewed' ? 'is-active' : '' }}"
            @if ($currentStatus === 'reviewed') aria-current="page" @endif>
            <div class="tb-stat-label">已審核</div>
            <div class="tb-stat-value tb-c-accent">{{ $counts['reviewed'] }}</div>
        </a>
    </nav>

    @if ($reservations->isEmpty() && $search)
        <div class="tb-card tb-empty">找不到符合搜尋條件的申請。</div>
    @elseif ($reservations->isEmpty() && in_array($currentStatus, ['pending', 'reviewed'], true))
        <div class="tb-card tb-empty">沒有{{ $currentStatus === 'pending' ? '未審核' : '已審核' }}的申請。</div>
    @elseif ($reservations->isEmpty())
        <div class="tb-card tb-empty">
            <i class="bi bi-calendar-x" aria-hidden="true"></i>
            <h2 class="h5 mt-3" style="color: var(--tb-text)">目前沒有預約紀錄</h2>
            <p class="mb-3">可以按右上角「空間預約」提出申請。</p>
            <a href="{{ route('reservations.create') }}" class="tb-btn tb-btn-outline">前往空間預約</a>
        </div>
    @else
        {{-- 申請表格：螢幕夠大用表格、不夠用清單（全站自動切換）；點一列在畫面中的對話框打開「預約詳細」（可以直接審核）。 --}}
        <section class="tb-table-wrap" data-no-fit aria-label="預約申請列表">
            @include('reservations._table', ['reservations' => $reservations])
        </section>

        {{-- 分頁：全站共用 layouts/pager（左邊「顯示第 1 到 15 筆，共 N 筆」，右邊切換頁數）。 --}}
        @include('layouts.pager', ['paginator' => $reservations])
    @endif
</div>

@endsection
