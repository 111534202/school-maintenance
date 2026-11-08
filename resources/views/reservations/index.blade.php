@extends('layouts.app')

@section('title', '空間預約')

@section('content')
@php
    $currentStatus = request('status');
@endphp
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
        {{-- 清單用表格：洋蔥式會自動把它變成一列一列的簡短清單（點一筆，詳細內容從右邊滑進來），手機版一次只放一個畫面；
             也可以切換回完整表格。整列都可以點進預約詳細頁（點擊與游標樣式見 layouts/app.blade.php 與 theme-b.css 的 tr[data-href]）。 --}}
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 reservation-rows">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">申請時間</th>
                            <th>申請人</th>
                            <th>申請班級</th>
                            <th class="text-nowrap">申請狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            <tr data-href="{{ route('reservations.show', $reservation) }}" role="link" aria-label="查看 {{ $reservation->class_name }} 的預約申請">
                                <td class="text-nowrap">{{ $reservation->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $reservation->requester?->name ?? '使用者資料不存在' }}</td>
                                <td>{{ $reservation->class_name }}</td>
                                <td>
                                    @if ($reservation->isReviewed())
                                        <span class="badge text-bg-success">已審核</span>
                                    @else
                                        <span class="badge text-bg-warning">未審核</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $reservations->links() }}</div>
    @endif
</div>
@endsection
