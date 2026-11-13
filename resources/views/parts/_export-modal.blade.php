{{-- 零件清單匯出視窗：先預覽，勾選要留下的欄位（上方）與零件（每一列左邊），再按「匯出 Excel」或「匯出 PDF」。
     零件範圍＝$exportParts（零件庫存列表：目前搜尋條件下的全部零件，不分頁；低庫存視窗：全部低庫存品項）；
     欄位與零件履歷「基本資料」相同。$modalId 讓同一頁可以放兩個（零件清單、低庫存品項）。 --}}
@php($exportColumns = \App\Support\PartExportColumns::COLUMNS)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ route('parts.export', 'excel') }}" class="modal-content parts-export-form" id="{{ $modalId }}Form">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Title">{{ $modalTitle }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @if ($withKeyword && request()->filled('keyword'))
                    <input type="hidden" name="keyword" value="{{ request('keyword') }}">
                    <p class="small text-muted">目前搜尋「{{ request('keyword') }}」，只列出符合的零件。</p>
                @endif

                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold fs-6 mb-2">匯出欄位</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($exportColumns as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $key }}" id="{{ $modalId }}_col_{{ $key }}" data-export-col="{{ $key }}" checked>
                                <label class="form-check-label" for="{{ $modalId }}_col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold">預覽</span>
                    <span class="small text-muted" data-export-summary aria-live="polite"></span>
                </div>
                <div class="table-responsive">
                    {{-- data-no-onion：這張是預覽用的表格，不要變成清單。 --}}
                    <table class="tb-table tb-table-sm align-middle mb-0" data-no-onion>
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 2.5rem;">
                                    <input class="form-check-input" type="checkbox" data-export-all checked aria-label="全選／全不選零件">
                                </th>
                                @foreach ($exportColumns as $key => $label)
                                    <th class="text-nowrap {{ in_array($key, \App\Support\PartExportColumns::NUMERIC, true) ? 'text-end' : '' }}" data-export-cell="{{ $key }}">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exportParts as $part)
                                @php($values = \App\Support\PartExportColumns::values($part))
                                <tr>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="ids[]" value="{{ $part->id }}" data-export-row checked aria-label="匯出 {{ $part->part_code }}">
                                    </td>
                                    @foreach ($exportColumns as $key => $label)
                                        <td class="{{ in_array($key, \App\Support\PartExportColumns::NUMERIC, true) ? 'text-end text-nowrap' : '' }}" data-export-cell="{{ $key }}">{{ $values[$key] }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-success" formaction="{{ route('parts.export', 'excel') }}" data-export-submit>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 匯出 Excel
                </button>
                <button type="submit" class="btn btn-danger" formaction="{{ route('parts.export', 'pdf') }}" data-export-submit>
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> 匯出 PDF
                </button>
            </div>
        </form>
    </div>
</div>

@once
<script>
    // 匯出預覽（每個 .parts-export-form 各自運作）：取消勾選的欄位在預覽裡隱藏；沒有勾選任何欄位或零件時不能匯出。
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.parts-export-form').forEach(function (form) {
        var all = form.querySelector('[data-export-all]');
        var summary = form.querySelector('[data-export-summary]');
        var rows = Array.prototype.slice.call(form.querySelectorAll('[data-export-row]'));
        var cols = Array.prototype.slice.call(form.querySelectorAll('[data-export-col]'));

        function refresh() {
            cols.forEach(function (c) {
                form.querySelectorAll('[data-export-cell="' + c.dataset.exportCol + '"]').forEach(function (cell) { cell.hidden = !c.checked; });
            });
            var rowCount = rows.filter(function (r) { return r.checked; }).length;
            var colCount = cols.filter(function (c) { return c.checked; }).length;
            all.checked = rowCount === rows.length;
            all.indeterminate = rowCount > 0 && rowCount < rows.length;
            summary.textContent = '已選 ' + rowCount + ' / ' + rows.length + ' 項零件、' + colCount + ' 個欄位';
            form.querySelectorAll('[data-export-submit]').forEach(function (b) { b.disabled = rowCount === 0 || colCount === 0; });
        }

        all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); });
        form.addEventListener('change', refresh);
        refresh();
    }); });
</script>
@endonce
