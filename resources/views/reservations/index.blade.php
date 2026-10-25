@extends('layouts.app')

@section('title', '空間預約')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">空間預約</h1>
            <p class="text-muted mb-0">查看預約申請，或提出新的教室借用申請。</p>
        </div>

        <a href="{{ route('reservations.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            新增預約
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-bold mb-1">操作未完成，請檢查以下內容：</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($reservations->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <h2 class="h5 mt-3">目前沒有預約紀錄</h2>
                    <p class="text-muted mb-3">可以點選「新增預約」提出申請。</p>
                    <a href="{{ route('reservations.create') }}" class="btn btn-outline-primary">
                        新增預約
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-onion-primary="1,3">
                        <thead class="table-light">
                            <tr>
                                <th>預約日期</th>
                                <th>教室</th>
                                <th>班級</th>
                                <th>使用時段</th>
                                @if (auth()->user()->hasPermission('reservations.manage'))
                                    <th>申請人</th>
                                @endif
                                <th>用途</th>
                                <th>申請狀態</th>
                                @if (auth()->user()->hasPermission('reservations.manage'))
                                    <th>審核操作</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $reservation)
                                <tr>
                                    <td>{{ $reservation->reservation_date->format('Y-m-d') }}</td>
                                    <td>{{ $reservation->classroom?->name ?? '教室資料不存在' }}</td>
                                    <td>{{ $reservation->class_name }}</td>
                                    <td>
                                        {{ substr($reservation->start_time, 0, 5) }}
                                        –
                                        {{ substr($reservation->end_time, 0, 5) }}
                                    </td>

                                    @if (auth()->user()->hasPermission('reservations.manage'))
                                        <td>{{ $reservation->requester?->name ?? '使用者資料不存在' }}</td>
                                    @endif

                                    <td>{{ $reservation->purpose ?: '—' }}</td>

                                    <td>
                                        @switch($reservation->status)
                                            @case('pending')
                                                <span class="badge text-bg-warning">待審核</span>
                                                @break
                                            @case('approved')
                                                <span class="badge text-bg-success">已核准</span>
                                                @break
                                            @case('rejected')
                                                <span class="badge text-bg-danger">已駁回</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge text-bg-secondary">已取消</span>
                                                @break
                                            @default
                                                <span class="badge text-bg-secondary">
                                                    {{ $reservation->status }}
                                                </span>
                                        @endswitch

                                        @if ($reservation->status === 'rejected' && $reservation->rejection_reason)
                                            <div class="small text-danger mt-1">
                                                拒絕原因：{{ $reservation->rejection_reason }}
                                            </div>
                                        @endif

                                        @if ($reservation->reviewed_at)
                                            <div class="small text-muted mt-1">
                                                審核時間：{{ $reservation->reviewed_at->format('Y-m-d H:i') }}
                                            </div>
                                        @endif
                                    </td>

                                    @if (auth()->user()->hasPermission('reservations.manage'))
                                        <td>
                                            @if ($reservation->status === 'pending')
                                                <div class="d-flex flex-column gap-2" style="min-width: 180px;">
                                                    <form
                                                        method="POST"
                                                        action="{{ route('reservations.approve', $reservation) }}"
                                                        onsubmit="return confirm('確定要核准這筆預約嗎？');"
                                                    >
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success w-100">
                                                            <i class="bi bi-check-circle me-1"></i>
                                                            核准
                                                        </button>
                                                    </form>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('reservations.reject', $reservation) }}"
                                                        onsubmit="return confirm('確定要拒絕這筆預約嗎？');"
                                                    >
                                                        @csrf
                                                        <label
                                                            for="rejection_reason_{{ $reservation->id }}"
                                                            class="form-label small mb-1"
                                                        >
                                                            拒絕原因（必填）
                                                        </label>
                                                        <textarea
                                                            id="rejection_reason_{{ $reservation->id }}"
                                                            name="rejection_reason"
                                                            class="form-control form-control-sm mb-2"
                                                            rows="2"
                                                            maxlength="500"
                                                            required
                                                            placeholder="請說明拒絕原因"
                                                        ></textarea>
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                            <i class="bi bi-x-circle me-1"></i>
                                                            拒絕
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted small">已完成審核</span>
                                                @if ($reservation->reviewer)
                                                    <div class="small text-muted mt-1">
                                                        審核人：{{ $reservation->reviewer->name }}
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $reservations->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection