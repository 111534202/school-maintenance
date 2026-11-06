{{-- 零件清單匯出視窗：先預覽，勾選要留下的欄位（上方）與零件（每一列左邊），再按「匯出 Excel」或「匯出 PDF」。
     零件範圍＝目前搜尋條件下的全部零件（不分頁）；欄位與零件履歷「基本資料」相同。 --}}
@php($exportColumns = \App\Support\PartExportColumns::COLUMNS)
<div class="modal fade" id="partsExportModal" tabindex="-1" aria-labelledby="partsExportTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ route('parts.export', 'excel') }}" class="modal-content" id="partsExportForm">
            <div class="modal-header">
                <h2 class="modal-title h5" id="partsExportTitle">匯出零件清單</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @if (request()->filled('keyword'))
                    <input type="hidden" name="keyword" value="{{ request('keyword') }}">
                    <p class="small text-muted">目前搜尋「{{ request('keyword') }}」，只列出符合的零件。</p>
                @endif

                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold fs-6 mb-2">匯出欄位</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($exportColumns as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $key }}" id="export_col_{{ $key }}" data-export-col="{{ $key }}" checked>
                                <label class="form-check-label" for="export_col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold">預覽</span>
                    <span class="small text-muted" id="partsExportSummary" aria-live="polite"></span>
                </div>
                <div class="table-responsive">
                    {{-- data-no-onion：這張是預覽用的表格，不要變成清單。 --}}
                    <table class="table table-bordered table-sm align-middle mb-0" data-no-onion>
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 2.5rem;">
                                    <input class="form-check-input" type="checkbox" id="partsExportAll" checked aria-label="全選／全不選零件">
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

<script>
    // 匯出預覽：取消勾選的欄位在預覽裡隱藏；沒有勾選任何欄位或零件時不能匯出。
    (function () {
        var form = document.getElementById('partsExportForm');
        if (!form) return;
        var all = document.getElementById('partsExportAll');
        var summary = document.getElementById('partsExportSummary');
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
    })();
</script>
