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

    {{-- 申請狀態篩選：主控台「空間預約待審核」卡片會連到「未審核」。 --}}
    @php($currentStatus = request('status'))
    <div class="btn-group mb-3" role="group" aria-label="申請狀態篩選">
        <a href="{{ route('reservations.index') }}" class="btn btn-sm {{ in_array($currentStatus, ['pending', 'reviewed'], true) ? 'btn-outline-secondary' : 'btn-secondary' }}">全部</a>
        <a href="{{ route('reservations.index', ['status' => 'pending']) }}" class="btn btn-sm {{ $currentStatus === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">未審核</a>
        <a href="{{ route('reservations.index', ['status' => 'reviewed']) }}" class="btn btn-sm {{ $currentStatus === 'reviewed' ? 'btn-success' : 'btn-outline-success' }}">已審核</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($reservations->isEmpty() && in_array($currentStatus, ['pending', 'reviewed'], true))
                <p class="text-center text-muted py-5 mb-0">沒有{{ $currentStatus === 'pending' ? '未審核' : '已審核' }}的申請。</p>
            @elseif ($reservations->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <h2 class="h5 mt-3">目前沒有預約紀錄</h2>
                    <p class="text-muted mb-3">可以點選「新增預約」提出申請。</p>
                    <a href="{{ route('reservations.create') }}" class="btn btn-outline-primary">
                        新增預約
                    </a>
                </div>
            @else
                {{-- 點這一行的任何地方都會進入預約詳細頁（預約日期、時段、用途、審核結果都在詳細頁）。 --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 reservation-rows" data-onion-primary="3,4">
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
                                <tr class="reservation-row" data-href="{{ route('reservations.show', $reservation) }}" tabindex="0" role="link"
                                    aria-label="查看 {{ $reservation->class_name }} 的預約申請">
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

                <div class="mt-3">
                    {{ $reservations->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
<style>
    .reservation-row { cursor: pointer; }
    .reservation-row:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
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
