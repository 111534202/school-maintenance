@extends('layouts.app')

@section('title', '預約詳細：' . $reservation->class_name)

@section('content')
@php
    [$statusText, $statusColor] = $reservation->statusLabel();
    $canReview = $canManage && ! $reservation->isReviewed();
    $inModal = request()->boolean('modal');   // 在空間申請列表的對話框裡打開
    $weekdays = ['日', '一', '二', '三', '四', '五', '六'];
    // 舊資料（以前可以分時段審核）的「部分核准」：每個時段各自標示同意／拒絕。
    $showSlotResult = $reservation->status === \App\Models\ClassroomReservation::STATUS_PARTIALLY_APPROVED;
@endphp

{{-- 預約詳細：空間申請列表點一筆時，在畫面中的對話框（標題「預約詳細」）裡打開這一頁（?modal=1，不顯示頁首）；
     直接開網址時是一般頁面。內容依序：申請狀態、送出時間、教室、申請班級、申請人、申請時段、用途。 --}}
@unless ($inModal)
    <h1 class="h3 mb-3">預約詳細</h1>
@endunless

<div class="{{ $inModal ? '' : 'card p-4' }}" style="max-width: 720px;">
    <dl class="mb-0 reservation-detail" id="reservationDetail">
        <dt>申請狀態</dt>
        <dd><span class="badge text-bg-{{ $statusColor }}">{{ $statusText }}</span></dd>

        <dt>送出時間</dt>
        <dd>{{ $reservation->created_at?->format('Y-m-d H:i') }}</dd>

        <dt>教室</dt>
        <dd>{{ $reservation->classroom ? $reservation->classroom->name . '（' . $reservation->classroom->classroom_code . '）' : '教室資料不存在' }}</dd>

        <dt>申請班級</dt>
        <dd>{{ $reservation->class_name }}</dd>

        <dt>申請人</dt>
        <dd>{{ $reservation->requester?->name ?? '使用者資料不存在' }}</dd>

        {{-- 申請時段只顯示日期與節次；起訖時間仍記在資料裡（判斷重疊、時間是否已過），沒有節次的時段才顯示時間。 --}}
        <dt>申請時段</dt>
        <dd>
            <ul class="list-unstyled mb-0">
                @foreach ($reservation->slots as $slot)
                    <li>
                        {{ $slot->reservation_date->format('Y-m-d') }}（{{ $weekdays[$slot->reservation_date->dayOfWeek] }}）{{ $slot->periodOrTime() }}
                        @if ($showSlotResult)
                            @php($slotApproved = $slot->effectiveStatus() === 'approved')
                            <span class="badge text-bg-{{ $slotApproved ? 'success' : 'danger' }} ms-1">{{ $slotApproved ? '同意' : '拒絕' }}</span>
                            @if (! $slotApproved && $slot->rejection_reason)
                                <span class="small text-muted ms-1">{{ $slot->rejection_reason }}</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </dd>

        <dt>用途</dt>
        <dd class="rd-wrap">{{ $reservation->purpose ?: '—' }}</dd>

        @if ($reservation->isReviewed())
            <dt>審核人</dt>
            {{-- 已審核但沒有審核人＝系統自動拒絕（時段重疊、或時間已過仍未審核）。 --}}
            <dd>{{ $reservation->reviewer?->name ?? '系統自動' }}</dd>

            <dt>審核時間</dt>
            <dd>{{ $reservation->reviewed_at?->format('Y-m-d H:i') ?? '—' }}</dd>

            @if ($reservation->status === 'rejected')
                <dt>拒絕原因</dt>
                <dd class="rd-wrap {{ $reservation->rejection_reason ? 'text-danger' : '' }}">{{ $reservation->rejection_reason ?: '—' }}</dd>
            @endif
        @endif
    </dl>

    {{-- 教室設備狀況：沒有設備在報修就不顯示。每次打開都重新檢查，所以送出申請後才報修的設備也會出現在這裡。 --}}
    @if ($repairNotices || $classroomSuspended)
        <div class="alert {{ $classroomSuspended ? 'alert-danger' : 'alert-warning' }} small mt-3 mb-0">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>教室設備報修中</div>
            @if ($repairNotices)
                <ul class="mb-0 ps-3">
                    @foreach ($repairNotices as $notice)
                        <li>
                            {{ $notice['text'] }}
                            @if ($notice['core'])<span class="badge text-bg-danger ms-1">核心設備</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($classroomSuspended)
                <div class="mt-1">核心設備正在維修，這間教室目前暫停預約，無法核准（仍可拒絕）。</div>
            @endif
        </div>
    @endif

    {{-- 審核：一筆申請只能整筆核准或整筆拒絕。拒絕原因選填；
         核准後，和這筆時段重疊的其他待審核申請會整筆自動拒絕（下面黃色框先列出來，按核准時再確認一次）。 --}}
    {{-- 欄位名稱與內容：放得下就左右並排；除了「用途」「拒絕原因」（可能很長、會自動換行）以外，
         只要有一項內容在這個寬度放不下，才改成名稱在上、內容在下（is-stacked）。 --}}
    <style>
        .reservation-detail { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 0.5rem 2.5rem; }
        .reservation-detail dt { font-weight: 700; }
        .reservation-detail dd { margin: 0; min-width: 0; white-space: nowrap; }
        .reservation-detail dd.rd-wrap { white-space: normal; overflow-wrap: anywhere; }
        .reservation-detail.is-stacked { grid-template-columns: minmax(0, 1fr); gap: 0; }
        .reservation-detail.is-stacked dt:not(:first-child) { margin-top: 0.75rem; }
        .reservation-detail.is-stacked dd { white-space: normal; }
    </style>
    <script>
        (function () {
            var dl = document.getElementById('reservationDetail');
            function layout() {
                dl.classList.remove('is-stacked');
                var tooWide = Array.prototype.some.call(dl.querySelectorAll('dd:not(.rd-wrap)'), function (dd) {
                    return dd.scrollWidth > dd.clientWidth + 1;
                });
                dl.classList.toggle('is-stacked', tooWide);
            }
            layout();
            window.addEventListener('resize', layout);
        })();
    </script>

    @if ($canReview)
        <form method="POST" action="{{ route('reservations.reject', $reservation) }}" class="mt-4" id="reviewForm">
            @csrf
            <label for="rejection_reason" class="form-label">拒絕原因（選填）</label>
            <input type="text" id="rejection_reason" name="rejection_reason" maxlength="500"
                class="form-control @error('rejection_reason') is-invalid @enderror"
                placeholder="例如：該時段已安排設備維護" value="{{ old('rejection_reason') }}">
            @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror

            @if ($willAutoReject)
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>核准後，下列時段重疊的待審核申請會整筆自動拒絕：
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($willAutoReject as $other)
                            <li>{{ $other['reservation']->class_name }}（重疊時段 {{ $other['slot'] }}）</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 核准在左、拒絕在最右邊，避免誤按。 --}}
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success" formaction="{{ route('reservations.approve', $reservation) }}" formnovalidate
                    @disabled($classroomSuspended)
                    data-confirm="確定要核准這筆申請嗎？{{ $willAutoReject ? '時段重疊的 ' . count($willAutoReject) . ' 筆待審核申請會自動拒絕。' : '' }}">核准</button>
                <button type="submit" class="btn btn-danger ms-auto" data-confirm="確定要拒絕這筆申請嗎？">拒絕</button>
            </div>
        </form>

        <script>
            // 按「核准」或「拒絕」前再確認一次。
            document.getElementById('reviewForm').addEventListener('submit', function (event) {
                var button = event.submitter;
                if (button && button.dataset.confirm && !window.confirm(button.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        </script>
    @endif
</div>
@endsection
