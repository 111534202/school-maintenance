{{-- 空間預約申請的一張卡片（空間申請列表、空間預約頁的「我的申請」共用）。
     點整張卡片在畫面中的對話框打開「預約詳細」。左邊方塊是申請班級的第一個字。
     $showResult＝true（我的申請）：狀態顯示實際結果（待審核／已核准／已拒絕），第二欄顯示教室（申請人都是自己）；
     false（空間申請）：狀態顯示未審核／已審核，第二欄顯示申請人。
     版面（theme-b.css 的 .tb-list-card）：寬的時候排成一列；變窄時申請狀態排到申請人下面，「>」永遠跟在申請狀態旁邊。 --}}
@php
    $showResult = $showResult ?? false;
    $requesterName = $reservation->requester?->name ?? '使用者資料不存在';
    [$resultText, $resultColor] = $reservation->statusLabel();
@endphp
<a href="{{ route('reservations.show', $reservation) }}" class="tb-list-card" data-modal-frame data-modal-title="預約詳細"
    aria-label="查看 {{ $reservation->class_name }} 的預約申請（{{ $showResult ? $resultText : ($reservation->isReviewed() ? '已審核' : '未審核') }}）">
    <span class="tb-avatar tb-avatar-lg tb-lc-avatar" aria-hidden="true">{{ mb_substr($reservation->class_name, 0, 1) }}</span>
    <span class="tb-list-main tb-lc-main">
        <span class="tb-list-label">申請班級</span>
        <span class="tb-list-title">{{ $reservation->class_name }}</span>
    </span>
    @if ($showResult)
        <span class="tb-list-meta tb-lc-person">
            <span class="tb-list-label">教室</span>
            <span>{{ $reservation->classroom?->name ?? '教室資料不存在' }}</span>
        </span>
    @else
        <span class="tb-list-meta tb-lc-person">
            <span class="tb-list-label">申請人</span>
            <span>{{ $requesterName }}</span>
        </span>
    @endif
    <span class="tb-list-meta tb-lc-time">
        <span class="tb-list-label">送出時間</span>
        <span class="tb-num">{{ $reservation->created_at?->format('Y-m-d H:i') }}</span>
    </span>
    <span class="tb-lc-end">
        <span class="tb-list-status">
            <span class="tb-list-label">申請狀態</span>
            @if ($showResult)
                <span class="tb-pill {{ match ($resultColor) { 'success' => 'tb-pill-ok', 'danger' => 'tb-pill-danger', 'warning' => 'tb-pill-warn', default => 'tb-pill-info' } }}">{{ $resultText }}</span>
            @elseif ($reservation->isReviewed())
                <span class="tb-pill tb-pill-ok">已審核</span>
            @else
                <span class="tb-pill tb-pill-warn">未審核</span>
            @endif
        </span>
        <i class="bi bi-chevron-right tb-list-arrow" aria-hidden="true"></i>
    </span>
</a>
