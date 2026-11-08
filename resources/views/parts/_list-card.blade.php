{{-- 零件一張卡片（零件庫存列表、低庫存視窗共用；跟空間申請一樣放在一大張卡片裡，一筆一列）。
     左邊是零件編號，右邊依序是名稱、規格與庫存狀態（⚠ 低庫存／庫存充足）。
     點一列一律先在畫面中的對話框（標題「零件庫存」）顯示基本資料（parts.summary），再按「查看詳細」進零件履歷；
     連結本身也指向 summary，不會直接跳到零件履歷。 --}}
<a href="{{ route('parts.summary', $part) }}" class="tb-list-card tb-part-card" data-modal-frame data-modal-title="零件庫存" aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件資料">
    <span class="tb-list-main tb-lc-main">
        <span class="tb-list-label">零件編號</span>
        <span class="tb-list-title">{{ $part->part_code }}</span>
    </span>
    <span class="tb-list-meta tb-lc-name">
        <span class="tb-list-label">名稱</span>
        <span>{{ $part->name }}</span>
    </span>
    <span class="tb-list-meta tb-lc-spec">
        <span class="tb-list-label">規格</span>
        <span>{{ $part->specification ?? '—' }}</span>
    </span>
    <span class="tb-lc-end">
        <span class="tb-list-status">
            <span class="tb-list-label">庫存狀態</span>
            @if ($part->isLowStock())
                <span class="tb-pill tb-pill-danger">⚠ 低庫存</span>
            @else
                <span class="tb-pill tb-pill-ok">庫存充足</span>
            @endif
        </span>
        <i class="bi bi-chevron-right tb-list-arrow" aria-hidden="true"></i>
    </span>
</a>
