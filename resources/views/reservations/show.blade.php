@extends('layouts.app')

@section('title', '預約詳細：' . $reservation->class_name)

@section('content')
@php
    [$statusText, $statusColor] = $reservation->statusLabel();
    $canReview = $canManage && ! $reservation->isReviewed();
    $undecided = $reservation->slots->whereNull('status');
    $inModal = request()->boolean('modal');   // 在空間申請列表的對話框裡打開
    $weekdays = ['日', '一', '二', '三', '四', '五', '六'];
    // 逐節審核：只要有任何一節已經有結果（例如部分核准、或有一節被別人借走而自動拒絕），每一節各自標示同意／拒絕／待審核。
    $showSlotResult = $reservation->slots->whereNotNull('status')->isNotEmpty() && $reservation->status !== \App\Models\ClassroomReservation::STATUS_REJECTED;
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
                            @php($slotStatus = $slot->status)
                            <span class="badge text-bg-{{ $slotStatus === 'approved' ? 'success' : ($slotStatus === 'rejected' ? 'danger' : 'warning') }} ms-1">{{ $slotStatus === 'approved' ? '同意' : ($slotStatus === 'rejected' ? '拒絕' : '待審核') }}</span>
                            @if ($slotStatus === 'rejected' && $slot->rejection_reason)
                                <span class="small text-muted ms-1 text-wrap">{{ $slot->rejection_reason }}</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </dd>

        <dt>用途</dt>
        <dd class="rd-wrap">{{ $reservation->purpose ?: '—' }}</dd>

        {{-- 申請時勾選「其中一節無法借用就整筆不要」（只能整筆同意或拒絕）才顯示：只有這句紅字粗體，不加欄位名稱，佔滿一整行。 --}}
        @if ($reservation->is_all_or_nothing)
            <dd class="rd-wrap rd-full text-danger fw-bold">其中一節無法借用就整筆不要</dd>
        @endif

        @if ($reservation->isReviewed())
            <dt>審核人</dt>
            {{-- 已審核但沒有審核人＝系統自動拒絕（節次已被別人借走）。 --}}
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
        .reservation-detail dd.rd-full { grid-column: 1 / -1; }
        .reservation-detail.is-stacked dd.rd-full { margin-top: 0.75rem; }
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

    {{-- 審核：逐節選「同意」或「拒絕」後送出。申請人選了整筆（is_all_or_nothing）時，只能全部同意或全部拒絕。
         同意的節次如果已經核准給別人就不能選同意（畫面上會標出來）；同意後，其他申請裡重疊的節次會自動拒絕。
         拒絕原因選填，套用在這次被拒絕的節次。按送出時會先跳出確認視窗（data-confirm）。 --}}
    @if ($canReview)
        @php($conflictTexts = collect(app(\App\Services\ReservationReviewService::class)->conflictLabels($reservation, $undecided))->keyBy('slot'))
        <form method="POST" action="{{ route('reservations.review', $reservation) }}" class="mt-4" id="reviewForm"
            data-confirm="確定要送出這筆申請的審核結果嗎？{{ $willAutoReject ? '同意的節次如果和其他待審核申請重疊，那些節次會自動拒絕。' : '' }}" data-confirm-ok="確認送出">
            @csrf
            <div class="fw-semibold mb-2">
                審核
                @if ($reservation->is_all_or_nothing)
                    <span class="small text-muted fw-normal ms-1">申請人選了整筆：只能全部同意或全部拒絕</span>
                @endif
            </div>
            <div class="list-group mb-2" id="slotDecisions">
                @foreach ($undecided as $slot)
                    @php($blocked = $conflictTexts->get($slot->label()))
                    <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                        <span class="me-auto">{{ $slot->reservation_date->format('Y-m-d') }}（{{ $weekdays[$slot->reservation_date->dayOfWeek] }}）{{ $slot->periodOrTime() }}
                            @if ($blocked)<span class="small text-danger ms-1">已核准給 {{ $blocked['class_name'] }}</span>@endif
                        </span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="{{ $slot->label() }} 的審核結果">
                            <input type="radio" class="btn-check" name="decisions[{{ $slot->id }}]" id="approve_{{ $slot->id }}" value="approved"
                                @checked(! $blocked && old("decisions.{$slot->id}", 'approved') === 'approved') @disabled($blocked || $classroomSuspended)>
                            <label class="btn btn-outline-success" for="approve_{{ $slot->id }}">同意</label>
                            <input type="radio" class="btn-check" name="decisions[{{ $slot->id }}]" id="reject_{{ $slot->id }}" value="rejected"
                                @checked($blocked || $classroomSuspended || old("decisions.{$slot->id}") === 'rejected')>
                            <label class="btn btn-outline-danger" for="reject_{{ $slot->id }}">拒絕</label>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($undecided->count() > 1)
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-link btn-sm p-0" data-decide-all="approved" @disabled($classroomSuspended)>全部同意</button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-decide-all="rejected">全部拒絕</button>
                </div>
            @endif

            <label for="rejection_reason" class="form-label">拒絕原因（選填，套用在被拒絕的節次）</label>
            <input type="text" id="rejection_reason" name="rejection_reason" maxlength="500"
                class="form-control @error('rejection_reason') is-invalid @enderror"
                placeholder="例如：該時段已安排設備維護" value="{{ old('rejection_reason') }}">
            @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror

            @if ($willAutoReject)
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>同意後，下列申請因為時段重疊、而且選了「其中一節無法借用就整筆不要」，會整筆自動拒絕：
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($willAutoReject as $other)
                            <li>{{ $other['reservation']->class_name }}（重疊 {{ $other['slot'] }}）</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">送出審核</button>
            </div>
        </form>

        <script>
            (function () {
                var form = document.getElementById('reviewForm');
                var allOrNothing = @json((bool) $reservation->is_all_or_nothing);
                // 「全部同意／全部拒絕」：一次選好每一節（已借給別人的節次不能同意）。
                form.querySelectorAll('[data-decide-all]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var value = button.getAttribute('data-decide-all');
                        form.querySelectorAll('input[type=radio][value="' + value + '"]:not(:disabled)').forEach(function (r) { r.checked = true; });
                    });
                });
                // 整筆制：改其中一節，其他節跟著一起改（只能全部同意或全部拒絕）。
                if (allOrNothing) {
                    form.addEventListener('change', function (event) {
                        if (!event.target.matches('input[type=radio]')) return;
                        form.querySelectorAll('input[type=radio][value="' + event.target.value + '"]:not(:disabled)').forEach(function (r) { r.checked = true; });
                    });
                }
            })();
        </script>
    @endif
</div>
@endsection
