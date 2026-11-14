{{-- 空間申請表格（空間申請列表、我的申請共用）。螢幕夠大用表格、不夠用清單，由全站的表格／清單自動切換處理
     （layouts/partials/list-onion＋auto-table）。清單模式：標題＝申請班級，副標題＝申請人（我的申請是教室）· 申請時段，右邊是申請狀態。
     點一列在畫面中的對話框打開「預約詳細」（可直接審核）。
     $mine＝true（我的申請）：不顯示申請人（都是自己）；狀態兩種都顯示實際結果（待審核／已核准／部分核准／已拒絕）；
     false（空間申請）：同樣顯示實際結果（待審核／已核准／部分核准／已拒絕），另外多一欄申請人。 --}}
@php($mine = $mine ?? false)
<div class="table-responsive">
    <table class="tb-table tb-table-hover" aria-label="{{ $mine ? '我的申請' : '預約申請列表' }}">
        <thead>
            <tr>
                <th>申請班級</th>
                @unless ($mine)<th>申請人</th>@endunless
                <th>教室</th>
                <th class="text-nowrap">申請時段</th>
                <th class="text-nowrap">送出時間</th>
                <th class="text-nowrap">申請狀態</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reservations as $reservation)
                @php([$resultText, $resultColor] = $reservation->statusLabel())
                @php([$slotText, $slotCount] = $reservation->slotSummary())
                <tr data-modal-frame-href="{{ route('reservations.show', $reservation) }}" data-modal-title="預約詳細" tabindex="0" role="link"
                    aria-label="查看 {{ $reservation->class_name }} 的預約申請（{{ $resultText }}）">
                    <td class="fw-semibold">{{ $reservation->class_name }}</td>
                    @unless ($mine)<td><span class="tb-ellipsis tb-cell-person" title="{{ $reservation->requester?->name }}">{{ $reservation->requester?->name ?? '使用者資料不存在' }}</span></td>@endunless
                    <td><span class="tb-ellipsis tb-cell-room" title="{{ $reservation->classroom?->name }}">{{ $reservation->classroom?->name ?? '教室資料不存在' }}</span></td>
                    <td class="text-nowrap">
                        {{ $slotText }}
                        @if ($slotCount > 1)<span class="small text-muted">（共 {{ $slotCount }} 節）</span>@endif
                    </td>
                    <td class="text-nowrap tb-num">{{ $reservation->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="text-nowrap">
                        {{-- 一律顯示實際結果：待審核（黃）、已核准（綠）、部分核准（藍）、已拒絕（紅），不再把核准與拒絕都寫成「已審核」。 --}}
                        <span class="badge tb-pill {{ match ($resultColor) { 'success' => 'tb-pill-ok', 'danger' => 'tb-pill-danger', 'warning' => 'tb-pill-warn', default => 'tb-pill-info' } }}">{{ $resultText }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
