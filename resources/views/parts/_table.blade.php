{{-- 零件表格（零件庫存列表、低庫存視窗共用）。螢幕夠大用表格、不夠用清單，由全站的表格／清單自動切換處理
     （layouts/partials/list-onion＋auto-table）。清單模式：標題＝零件編號，副標題＝名稱 · 規格，右邊是庫存狀態。
     點一列先在畫面中的對話框（標題「零件庫存」）顯示基本資料（parts.summary）；
     表格模式時最右邊另有「查看履歷」按鈕，直接換頁到零件履歷（不開對話框）。清單模式沒有這個按鈕（點一筆開對話框，裡面有「查看履歷」）。 --}}
<div class="table-responsive">
    <table class="tb-table tb-table-hover" aria-label="{{ $label ?? '零件列表' }}">
        <thead>
            <tr>
                <th class="text-nowrap">零件編號</th>
                <th>名稱</th>
                <th>規格</th>
                <th>品牌/型號</th>
                <th class="text-end text-nowrap">平均單價</th>
                <th class="text-end text-nowrap">目前庫存</th>
                <th class="text-end text-nowrap">安全庫存</th>
                {{-- 庫存狀態與「查看履歷」只跟內容一樣寬（tb-col-fit），兩個貼在一起放在最右邊。 --}}
                <th class="tb-col-fit text-center">庫存狀態</th>
                <th class="text-end tb-col-fit">操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parts as $part)
                @php($average = $part->averageUnitPrice())
                <tr data-modal-frame-href="{{ route('parts.summary', $part) }}" data-modal-title="零件庫存" tabindex="0" role="link"
                    aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件資料">
                    <td class="tb-code text-nowrap">{{ $part->part_code }}</td>
                    <td><span class="tb-ellipsis tb-cell-name" title="{{ $part->name }}">{{ $part->name }}</span></td>
                    <td><span class="tb-ellipsis tb-cell-spec" title="{{ $part->specification }}">{{ $part->specification ?? '—' }}</span></td>
                    <td class="tb-muted">{{ $part->brandModel() ?? '—' }}</td>
                    <td class="text-end text-nowrap tb-num">{{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}</td>
                    <td class="text-end text-nowrap tb-num {{ $part->isLowStock() ? 'fw-bold tb-c-danger' : '' }}">{{ $part->withUnit($part->current_stock) }}</td>
                    <td class="text-end text-nowrap tb-num tb-muted">{{ $part->withUnit($part->safety_stock) }}</td>
                    <td class="tb-col-fit text-center">
                        @if ($part->isLowStock())
                            <span class="badge tb-pill tb-pill-danger">⚠ 低庫存</span>
                        @else
                            <span class="badge tb-pill tb-pill-ok">庫存充足</span>
                        @endif
                    </td>
                    <td class="text-end tb-col-fit">
                        <a href="{{ route('parts.show', $part) }}" class="tb-btn tb-btn-outline tb-btn-sm">查看履歷</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
