@extends('layouts.app')

@section('title', '預約詳細：' . $reservation->class_name)

@section('content')
@php
    [$statusText, $statusColor] = $reservation->statusLabel();
    $canReview = $canManage && ! $reservation->isReviewed();
    // 每個時段的狀態文字與顏色（時段沒有自己的結果時跟著整筆申請）。
    $slotLabel = fn ($slot) => match ($slot->effectiveStatus()) {
        'approved' => ['同意', 'success'],
        'rejected' => ['拒絕', 'danger'],
        default => ['待審核', 'warning'],
    };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">預約詳細：{{ $reservation->class_name }}</h1>
    <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary btn-sm">返回列表</a>
</div>

<div class="card mb-3">
    <div class="card-header">申請資料</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">申請狀態</dt>
            <dd class="col-sm-9"><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></dd>

            <dt class="col-sm-3">教室</dt>
            <dd class="col-sm-9">{{ $reservation->classroom ? $reservation->classroom->name . '（' . $reservation->classroom->classroom_code . '）' : '教室資料不存在' }}</dd>

            <dt class="col-sm-3">申請班級</dt>
            <dd class="col-sm-9">{{ $reservation->class_name }}</dd>

            <dt class="col-sm-3">用途</dt>
            <dd class="col-sm-9">{{ $reservation->purpose ?: '—' }}</dd>

            <dt class="col-sm-3">申請人</dt>
            <dd class="col-sm-9">{{ $reservation->requester?->name ?? '使用者資料不存在' }}</dd>

            <dt class="col-sm-3">申請時間</dt>
            <dd class="col-sm-9">{{ $reservation->created_at?->format('Y-m-d H:i') }}</dd>

            <dt class="col-sm-3">審核人</dt>
            <dd class="col-sm-9">{{ $reservation->reviewer?->name ?? '—' }}</dd>

            <dt class="col-sm-3">審核時間</dt>
            <dd class="col-sm-9">{{ $reservation->reviewed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
        </dl>
    </div>
</div>

{{-- 教室設備狀況：教室裡有設備在報修／故障時提醒申請人與審核人（核心設備故障時教室會暫停預約，無法核准）。 --}}
<div class="card mb-3 {{ $classroomSuspended ? 'border-danger' : ($repairNotices ? 'border-warning' : '') }}">
    <div class="card-header">教室設備狀況</div>
    <div class="card-body">
        @if ($repairNotices)
            <ul class="mb-0 ps-3">
                @foreach ($repairNotices as $notice)
                    <li class="{{ $notice['core'] ? 'text-danger' : 'text-warning-emphasis' }}">
                        <i class="bi bi-exclamation-triangle me-1"></i>{{ $notice['text'] }}
                        @if ($notice['core'])<span class="badge text-bg-danger ms-1">核心設備</span>@endif
                    </li>
                @endforeach
            </ul>
        @elseif (! $classroomSuspended)
            <span class="text-success"><i class="bi bi-check-circle me-1"></i>目前沒有設備在報修。</span>
        @endif
        @if ($classroomSuspended)
            <p class="small text-danger mt-2 mb-0">核心設備正在等待維修，這間教室目前暫停預約，無法核准任何時段（仍可拒絕）。</p>
        @endif
    </div>
</div>

{{-- 預約時段：每個時段分別審核。未審核時，有預約管理權限的人可以在這裡逐一選擇同意或拒絕。 --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>預約時段（{{ $reservation->slots->count() ?: 1 }}）</span>
        @if ($canReview)
            <span class="small text-muted">每個時段都要選「同意」或「拒絕」才能送出；拒絕請填寫原因。</span>
        @endif
    </div>

    @if ($canReview)
        <form method="POST" action="{{ route('reservations.review', $reservation) }}" id="reviewForm">
            @csrf
    @endif

    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">預約日期</th>
                    <th class="text-nowrap">使用時段</th>
                    <th class="text-nowrap">節次</th>
                    <th class="text-nowrap">審核結果</th>
                    <th>{{ $canReview ? '拒絕原因（拒絕時必填）' : '拒絕原因' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservation->slots as $slot)
                    @php([$slotText, $slotColor] = $slotLabel($slot))
                    <tr>
                        <td class="text-nowrap">{{ $slot->reservation_date->format('Y-m-d') }}</td>
                        <td class="text-nowrap">{{ substr((string) $slot->start_time, 0, 5) }}–{{ substr((string) $slot->end_time, 0, 5) }}</td>
                        <td class="text-nowrap">{{ $slot->period_no ? '第 ' . $slot->period_no . ' 節' : '—' }}</td>
                        <td class="text-nowrap">
                            @if ($canReview)
                                @php($old = old("decisions.{$slot->id}.decision"))
                                <div class="btn-group btn-group-sm" role="group" aria-label="{{ $slot->label() }} 的審核結果">
                                    <input type="radio" class="btn-check slot-decision" name="decisions[{{ $slot->id }}][decision]" id="approve_{{ $slot->id }}" value="approved" required @checked($old === 'approved')>
                                    <label class="btn btn-outline-success" for="approve_{{ $slot->id }}">同意</label>
                                    <input type="radio" class="btn-check slot-decision" name="decisions[{{ $slot->id }}][decision]" id="reject_{{ $slot->id }}" value="rejected" @checked($old === 'rejected')>
                                    <label class="btn btn-outline-danger" for="reject_{{ $slot->id }}">拒絕</label>
                                </div>
                            @else
                                <span class="badge text-bg-{{ $slotColor }}">{{ $slotText }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($canReview)
                                <input type="text" class="form-control form-control-sm slot-reason @error("decisions.{$slot->id}.reason") is-invalid @enderror"
                                    name="decisions[{{ $slot->id }}][reason]" maxlength="500" placeholder="例如：該時段已安排設備維護"
                                    value="{{ old("decisions.{$slot->id}.reason") }}" aria-label="{{ $slot->label() }} 的拒絕原因"
                                    @disabled($old !== 'rejected') @required($old === 'rejected')>
                                @error("decisions.{$slot->id}.reason")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @else
                                {{ $slot->effectiveStatus() === 'rejected' ? ($slot->rejection_reason ?? $reservation->rejection_reason ?? '—') : '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    {{-- 舊資料沒有時段明細：顯示整筆申請的時間（審核時系統會自動補上時段）。 --}}
                    <tr>
                        <td class="text-nowrap">{{ $reservation->reservation_date->format('Y-m-d') }}</td>
                        <td class="text-nowrap">{{ substr((string) $reservation->start_time, 0, 5) }}–{{ substr((string) $reservation->end_time, 0, 5) }}</td>
                        <td>—</td>
                        <td><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></td>
                        <td>{{ $reservation->rejection_reason ?? '—' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canReview)
            <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" id="approveAll">全部同意</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="rejectAll">全部拒絕</button>
                </div>
                <button type="submit" class="btn btn-primary">送出審核</button>
            </div>
        </form>
    @endif
</div>

@if ($canReview)
<script>
// 選「拒絕」才開放並要求填原因；選「同意」就清掉原因。「全部同意／全部拒絕」只是幫忙一次點選，仍要按「送出審核」。
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reviewForm');

    function sync(radio) {
        const row = radio.closest('tr');
        const reason = row.querySelector('.slot-reason');
        const rejected = row.querySelector('input[value="rejected"]').checked;
        reason.disabled = !rejected;
        reason.required = rejected;
        if (!rejected) { reason.value = ''; reason.classList.remove('is-invalid'); }
        if (rejected && radio.value === 'rejected' && radio.checked) { reason.focus(); }
    }

    form.querySelectorAll('.slot-decision').forEach(function (radio) {
        radio.addEventListener('change', function () { sync(radio); });
    });

    function setAll(value) {
        form.querySelectorAll('.slot-decision[value="' + value + '"]').forEach(function (radio) {
            radio.checked = true;
            sync(radio);
        });
        if (value === 'rejected') {
            const first = form.querySelector('.slot-reason:not([disabled])');
            if (first) { first.focus(); }
        }
    }
    document.getElementById('approveAll').addEventListener('click', function () { setAll('approved'); });
    document.getElementById('rejectAll').addEventListener('click', function () { setAll('rejected'); });
});
</script>
@endif
@endsection
